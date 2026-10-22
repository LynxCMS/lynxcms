<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\PageMoved;
use LynxCms\Core\Pages\BranchMover;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Pages\PathBuilder;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\FamilyRules;

/**
 * Moves a page (and its subtree) under another parent. Old paths stay and redirect.
 * Reordering among the current siblings is pages.sort.
 */
final class MovePage extends Action
{
    use ResolvesPages;

    public function __construct(
        private readonly PageGuard $guard,
        private readonly PathBuilder $paths,
        private readonly BranchMover $mover,
        private readonly FamilyRules $family,
        private readonly Trash $trash,
    ) {}

    public static function name(): string
    {
        return 'pages.move';
    }

    public static function permission(): string
    {
        return 'pages.move';
    }

    public static function description(): string
    {
        return 'Move a page and everything under it to another parent page. Old URLs keep working as redirects.';
    }

    public static function rules(): array
    {
        return ['page' => ['required'], 'parent' => ['required'], 'sort' => ['sometimes', 'integer', 'min:0']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the page to move.')->required(),
            'parent' => $schema->string()->description('Id or path of the new parent.')->required(),
            'sort' => $schema->integer()->description('0-based position among the new siblings. Omit to place the page last.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $parent = $this->resolvePageReference($input->get('parent'), 'parent');
        $this->guard->assertMovable($page);
        $this->assertCanMoveInto($page, $parent);
        $this->family->assertAllowed($page->template(), (int) $parent->id, 'parent');
        $context->authorize('pages.move', $page);
        $context->authorize('pages.move', $parent);

        $from = (int) $page->parentId;
        $moved = $this->mover->move($page, (int) $parent->id, $input->int('sort') ?? PHP_INT_MAX);

        $context->changes->updated('page', $page->id, "Move page {$page->name()} under {$parent->name()} ({$moved} page(s))", [
            'from' => $from,
            'to' => $parent->id,
        ]);
        $this->paths->rebuild((int) $page->id, descendants: true, changes: $context->changes);
        $context->event(new PageMoved((int) $page->id, $from, (int) $parent->id));

        return $page;
    }

    private function assertCanMoveInto(Page $page, Page $parent): void
    {
        if ($page->parentId === null) {
            throw ValidationException::withMessages(['page' => 'The home page cannot be moved.']);
        }

        if ($this->trash->contains((int) $page->id)) {
            throw ValidationException::withMessages(['page' => "Page [{$page->name()}] is in the trash. Take it out with pages.restore."]);
        }

        if ($this->trash->holds((int) $parent->id)) {
            throw ValidationException::withMessages(['parent' => 'Use pages.trash to move pages to the trash.']);
        }

        if ($page->parentId === $parent->id) {
            throw ValidationException::withMessages(['parent' => 'The page is already under this parent. Use pages.sort to change its position.']);
        }

        if (DB::table(Tables::PAGE_CLOSURE)->where('ancestor_id', $page->id)->where('descendant_id', $parent->id)->exists()) {
            throw ValidationException::withMessages(['parent' => 'A page cannot be moved under itself or its own descendants.']);
        }

        $this->assertNamesAreFree($page, $parent);
    }

    private function assertNamesAreFree(Page $page, Page $parent): void
    {
        foreach (DB::table(Tables::PAGE_LANGUAGES)->where('page_id', $page->id)->get() as $row) {
            $taken = DB::table(Tables::PAGE_LANGUAGES)->where('parent_id', $parent->id)
                ->where('language_id', $row->language_id)->where('name', $row->name)->where('page_id', '!=', $page->id)->exists();

            if ($taken) {
                throw ValidationException::withMessages(['parent' => 'The new parent already has a child named ['.Value::string($row->name).'].']);
            }
        }
    }
}
