<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\PageTrashed;
use LynxCms\Core\Pages\BranchMover;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Pages\PathBuilder;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Moves a page and everything under it to the trash (ADR-0035). The branch keeps its pages and
 * values; its top page is renamed "{id}-{name}" so trashed names never clash, and visitors get a
 * 404 at its URLs, old ones included, until pages.restore puts it back.
 */
final class TrashPage extends Action
{
    use ResolvesPages;

    public function __construct(
        private readonly PageGuard $guard,
        private readonly Trash $trash,
        private readonly BranchMover $mover,
        private readonly PathBuilder $paths,
    ) {}

    public static function name(): string
    {
        return 'pages.trash';
    }

    public static function permission(): string
    {
        return 'pages.delete';
    }

    public static function description(): string
    {
        return 'Move a page and all pages under it to the trash. Visitors can no longer reach them; pages.restore puts them back, pages.empty_trash deletes them for good.';
    }

    public static function rules(): array
    {
        return ['page' => ['required']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->string()->description('Id, default-language path or page name of the page.')->required()];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $this->assertTrashable($page);

        $ids = Value::ints(DB::table(Tables::PAGE_CLOSURE)->where('ancestor_id', $page->id)->pluck('descendant_id')->all());
        // Rule 1 of the trash plan: whatever would stop a delete stops trashing, so nothing locked ends up stuck there.
        $this->guard->assertDeletable($page, $ids);
        $context->authorizePages('pages.delete', $ids);

        $names = $this->names((int) $page->id);
        $ancestors = Value::ints(DB::table(Tables::PAGE_CLOSURE)->where('descendant_id', $page->parentId)->pluck('ancestor_id')->all());
        $this->trash->remember((int) $page->id, (int) $page->parentId, $page->sort, $names, $ancestors, $context->actor->kind.($context->actor->id === null ? '' : ':'.$context->actor->id));
        $this->prefixNames((int) $page->id, $names);

        $from = (int) $page->parentId;
        $this->mover->move($page, $this->trash->ensureRoot($context));
        $this->paths->rebuild((int) $page->id, descendants: true);

        $context->changes->updated('page', $page->id, "Trash page {$page->name()} and ".(count($ids) - 1).' page(s) under it', ['ids' => $ids, 'from' => $from]);
        $context->event(new PageTrashed((int) $page->id, $from, $ids));

        return $page;
    }

    private function assertTrashable(Page $page): void
    {
        $problem = match (true) {
            $page->parentId === null => 'The home page cannot be trashed.',
            $page->id === $this->trash->rootId() => 'The trash cannot be trashed.',
            $this->trash->contains((int) $page->id) => "Page [{$page->name()}] is already in the trash.",
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['page' => $problem]);
        }
    }

    /**
     * @return array<int, string> name by language id
     */
    private function names(int $pageId): array
    {
        $names = [];

        foreach (DB::table(Tables::PAGE_LANGUAGES)->where('page_id', $pageId)->get(['language_id', 'name']) as $row) {
            $names[Value::int($row->language_id)] = Value::string($row->name);
        }

        return $names;
    }

    /**
     * @param  array<int, string>  $names
     */
    private function prefixNames(int $pageId, array $names): void
    {
        foreach (array_filter($names, fn (string $name): bool => $name !== '') as $languageId => $name) {
            DB::table(Tables::PAGE_LANGUAGES)->where('page_id', $pageId)->where('language_id', $languageId)->update(['name' => $pageId.'-'.$name]);
        }
    }
}
