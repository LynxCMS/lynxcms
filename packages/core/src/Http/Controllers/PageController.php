<?php

declare(strict_types=1);

namespace LynxCms\Core\Http\Controllers;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LynxCms\Core\Http\PageAttributes;
use LynxCms\Core\Http\TemplateView;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\NotFoundPage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGates;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\PathResolver;
use LynxCms\Core\Pages\Publication;
use LynxCms\Core\Pages\UniqueNames;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Response;

/**
 * The catch-all front-end route: one indexed lookup on (language, path). An inactive (old) path
 * answers with a 301 to the page's current URL in the same language (ADR-0011). A one-segment URL
 * that matches nothing redirects to the unique page with that name, like ProcessWire (ADR-0029).
 * The page not found answers its own URL with status 404 too (ADR-0085).
 */
final readonly class PageController
{
    private const string PREVIEW_SESSION_KEY = 'lynx.preview_drafts';

    public function __construct(
        private PathResolver $paths,
        private PageLoader $loader,
        private LanguageContext $context,
        private ViewFactory $views,
        private LanguageRegistry $languages,
        private Publication $publication,
        private UniqueNames $uniqueNames,
        private PageGates $gates,
        private NotFoundPage $notFound,
    ) {}

    public function __invoke(Request $request): Response
    {
        // Before lynx:install there are no pages: any URL the app does not route is not found.
        abort_unless($this->languages->hasDefault(), 404);

        $language = $this->context->current();
        $path = Value::string($request->attributes->get('lynx.path', trim($request->path(), '/')));
        $match = $this->paths->resolve($language, $path);

        if ($match === null) {
            return $this->uniqueShortcut($language, $path, $request)
                ?? $this->otherLanguagePath($language, $path, $request)
                ?? abort(404);
        }

        [$pageId, $active] = $match;
        $page = $this->loader->find($pageId);

        abort_if($page === null || ! $page->template()->hasUrl(), 404);
        $isPublished = $this->publication->isPublished($page);
        abort_unless($isPublished || $this->mayViewUnpublished($page), 404);
        abort_unless($page->isActiveIn($language) || $language->id === $this->languages->default()->id, 404);

        if (! $active) {
            return $this->redirectTo($page->localUrl($language), $request);
        }

        // A module may answer instead of the page, e.g. with a password form (ADR-0062).
        $gate = $this->gates->respond($page, $request);

        if ($gate !== null) {
            return $gate;
        }

        $isPreviewing = $this->isPreviewingDrafts($request);
        $preview = $isPreviewing ? $this->draftPreview($page) : null;

        if (! $isPreviewing && $isPublished) {
            // The page that answers, for middleware and components such as a page cache (ADR-0064).
            $request->attributes->set(PageAttributes::PAGE, $page);
        }

        $html = TemplateView::render($page, $this->views, $preview);

        if (! $isPreviewing && $isPublished) {
            return $this->notFound->is($page) ? response($html, Response::HTTP_NOT_FOUND)->header('X-Robots-Tag', 'noindex') : response($html);
        }

        // Drafts and unpublished pages are for the editor's eyes only: never cached, never indexed.
        $html = $isPublished ? $html : $this->withUnpublishedBar($html);

        return response($isPreviewing ? $this->withPreviewBar($html, $request, $preview !== null) : $html)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * ?preview=draft turns draft previews on for the rest of the session, so following links or
     * switching language keeps showing drafts; ?preview=live turns them off (ADR-0039).
     */
    private function isPreviewingDrafts(Request $request): bool
    {
        if ($request->user() === null || ! $request->hasSession()) {
            return false;
        }

        match ($request->query('preview')) {
            'draft' => $request->session()->put(self::PREVIEW_SESSION_KEY, true),
            'live' => $request->session()->forget(self::PREVIEW_SESSION_KEY),
            default => null,
        };

        return (bool) $request->session()->get(self::PREVIEW_SESSION_KEY, false);
    }

    /** A small bar at the bottom of the page, so nobody mistakes a draft for the live page. */
    private function withPreviewBar(string $html, Request $request, bool $hasDraft): string
    {
        $note = $hasDraft ? 'This page shows its draft.' : 'This page has no draft: it shows the live version.';
        $bar = '<div data-lynx-preview style="position:fixed;inset-inline:0;bottom:0;z-index:2147483647;display:flex;gap:1rem;justify-content:center;align-items:center;padding:.5rem 1rem;font:14px/1.4 system-ui,sans-serif;color:#fff;background:#0e7490">'
            .'<strong>Previewing drafts.</strong> <span>'.e($note).'</span> '
            .'<a href="'.e($request->url().'?preview=live').'" style="color:#fff;text-decoration:underline">Exit preview</a></div>';

        return str_contains($html, '</body>') ? str_replace('</body>', $bar.'</body>', $html) : $html.$bar;
    }

    /**
     * A signed in user who may view the page in the admin sees it before it is published, so
     * View page works for a new page (ADR-0113). Pages in the trash stay out of reach.
     */
    private function mayViewUnpublished(Page $page): bool
    {
        if (! Gate::allows('lynx.pages.view', $page)) {
            return false;
        }

        $trash = lynx()->pages->trashId();

        return $trash === null || lynx()->pages->count("id={$page->id}, has_parent={$trash}, include=all") === 0;
    }

    /** A bar at the top of an unpublished page, so nobody mistakes it for what visitors see. */
    private function withUnpublishedBar(string $html): string
    {
        $bar = '<div data-lynx-unpublished style="position:sticky;top:0;z-index:2147483647;padding:.5rem 1rem;text-align:center;font:14px/1.4 system-ui,sans-serif;color:#fff;background:#b45309">'
            .'<strong>Not published.</strong> Visitors get a "not found" page here. You see it because you are signed in.</div>';

        return preg_match('/<body[^>]*>/i', $html, $body, PREG_OFFSET_CAPTURE) === 1
            ? substr_replace($html, $body[0][0].$bar, $body[0][1], strlen($body[0][0]))
            : $bar.$html;
    }

    /** The page with its draft's values, for a signed in user who may edit it (ADR-0039). */
    private function draftPreview(Page $page): ?Page
    {
        $draft = Gate::allows('lynx.pages.edit', $page) ? lynx()->pages->draft($page) : null;

        return $draft === null ? null : $page->withValuesOf($draft);
    }

    /** Only bare default-language names, and only to a published page with a URL. */
    private function uniqueShortcut(Language $language, string $path, Request $request): ?Response
    {
        $isBareName = $path !== '' && ! str_contains($path, '/') && $language->id === $this->languages->default()->id;
        $id = $isBareName ? $this->uniqueNames->pageNamed(mb_strtolower($path)) : null;
        $page = $id === null ? null : $this->loader->find($id);

        if ($page === null || ! $page->template()->hasUrl() || ! $this->publication->isPublished($page)) {
            return null;
        }

        return $this->redirectTo($page->localUrl($language), $request);
    }

    /**
     * A path written in another language ("/pt/courses/risk/" for "/pt/cursos/risco/") redirects
     * to the page in the URL's language, as when a visitor switches language by hand.
     */
    private function otherLanguagePath(Language $language, string $path, Request $request): ?Response
    {
        foreach ($this->languages->all() as $other) {
            $match = $other->id === $language->id || ! $other->is_active ? null : $this->paths->resolve($other, $path);
            $page = $match === null ? null : $this->loader->find($match[0]);

            if ($page === null || ! $page->template()->hasUrl() || ! $this->publication->isPublished($page)) {
                continue;
            }

            if ($page->isActiveIn($language) || $language->id === $this->languages->default()->id) {
                return $this->redirectTo($page->localUrl($language), $request);
            }
        }

        return null;
    }

    private function redirectTo(string $url, Request $request): Response
    {
        $query = $request->getQueryString();

        return redirect()->away(rtrim(url('/'), '/').$url.($query ? '?'.$query : ''), 301);
    }
}
