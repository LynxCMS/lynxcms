<?php

declare(strict_types=1);

namespace LynxCms\Core\Http;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\NotFoundPage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Answers a front-end 404 with the page not found (ADR-0085), in its template, in the visitor's
 * language, with status 404 and kept out of search engines. Requests it is not for (JSON, other
 * methods, the admin) and sites without one get Laravel's own 404. A page that fails to render
 * is reported and Laravel's 404 answers, so a broken template never turns a 404 into a 500.
 *
 * @internal
 */
final readonly class NotFoundResponder
{
    public function __construct(private NotFoundPage $notFound, private LanguageRegistry $languages, private ViewFactory $views) {}

    public function respond(Request $request): ?Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return null;
        }

        if ($request->expectsJson() || $request->routeIs('lynx.admin.*') || ! $this->languages->hasDefault()) {
            return null;
        }

        $page = $this->notFound->page();

        if ($page === null) {
            return null;
        }

        try {
            $html = TemplateView::render($page, $this->views);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return response($html, Response::HTTP_NOT_FOUND)->header('X-Robots-Tag', 'noindex');
    }
}
