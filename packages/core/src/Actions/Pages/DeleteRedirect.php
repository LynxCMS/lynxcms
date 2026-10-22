<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Change;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\OldPath;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Support\Tables;

/**
 * Forgets an old path of a page, so it stops redirecting (and becomes free for other pages without
 * a lookup tie). Only old paths: the page's current path changes by renaming or moving it.
 */
final class DeleteRedirect extends Action
{
    use ResolvesPages;

    public function __construct(private readonly PageGuard $guard, private readonly LanguageRegistry $languages) {}

    public static function name(): string
    {
        return 'redirects.delete';
    }

    public static function permission(): string
    {
        return 'pages.edit';
    }

    public static function description(): string
    {
        return 'Delete an old URL of a page so it no longer redirects to the page. '
            .'Old URLs are listed by $lynx->pages->redirects(); the current URL cannot be deleted.';
    }

    public static function rules(): array
    {
        return ['page' => ['required'], 'path' => ['required', 'string', 'max:2048'], 'language' => ['sometimes', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the page.')->required(),
            'path' => $schema->string()->description('The old path without language prefix, e.g. "courses/delivery-8".')->required(),
            'language' => $schema->string()->description('Language code of the old path. Defaults to the default language.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $this->guard->assertEditable($page);
        $context->authorize('pages.edit', $page);

        $language = $this->languages->find($input->string('language') ?? $this->languages->default()->code)
            ?? throw ValidationException::withMessages(['language' => 'Unknown language.']);
        $old = new OldPath($language, trim((string) $input->string('path'), '/'), true);

        $deleted = DB::table(Tables::PAGE_PATHS)
            ->where('page_id', $page->id)
            ->where('language_id', $language->id)
            ->where('active', false)
            ->whereRaw('lower(path) = ?', [mb_strtolower($old->path)])
            ->delete();

        if ($deleted === 0) {
            throw ValidationException::withMessages(['path' => "Page [{$page->name()}] has no old URL {$old->url()}."]);
        }

        // Filed under the page, so it shows in the page's activity; destructive because search engines may still link it.
        $context->changes->add(new Change(Change::UPDATED, 'page', $page->id, "Delete old URL {$old->url()} of page {$page->name()}", [
            'language' => $language->code,
            'path' => $old->path,
        ], destructive: true));

        return $page;
    }
}
