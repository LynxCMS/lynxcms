<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Revisions;
use LynxCms\Core\Strings\PageStringEdits;

/**
 * Puts a page's values back as they were in a revision (ADR-0038), as a new pages.save, so the
 * current values become a revision too. Fields the template no longer has are skipped; values
 * the revision did not have are cleared. The page's own template strings come back too (ADR-0048),
 * unless the revision predates them.
 */
final class RestoreRevision extends Action
{
    use ResolvesPages;

    public function __construct(private readonly Revisions $revisions, private readonly PageStringEdits $strings) {}

    public static function name(): string
    {
        return 'pages.restore_revision';
    }

    public static function permission(): string
    {
        return 'pages.edit';
    }

    public static function description(): string
    {
        return 'Restore the field values of a page from one of its revisions. The current values are kept as a revision.';
    }

    public static function rules(): array
    {
        return ['page' => ['required'], 'revision' => ['required', 'integer']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the page.')->required(),
            'revision' => $schema->integer()->description('Id of the revision, from get_page_history\'s revisions.')->required(),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $id = (int) $input->int('revision');
        $revision = $this->revisions->find($id);

        if ($revision === null || $revision['page'] !== $page->id) {
            throw ValidationException::withMessages(['revision' => "Revision [{$id}] is not a revision of page [{$page->name()}]."]);
        }

        $context->authorize('pages.edit', $page);
        $saved = $context->run(SavePage::class, array_filter([
            'id' => $page->id,
            'values' => $this->revisions->saveInput($page, $revision['values']),
            'strings' => $revision['strings'] === null ? null : $this->strings->replacement((int) $page->id, $revision['strings']),
        ], fn (mixed $value): bool => $value !== null));
        $context->changes->updated('page', $page->id, "Restore revision {$id} of page {$page->name()}", ['revision' => $id]);

        return $saved instanceof Page ? $saved : $page;
    }
}
