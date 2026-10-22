<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\PageRestored;
use LynxCms\Core\Pages\BranchMover;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PathBuilder;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Templates\FamilyRules;

/**
 * Takes a branch out of the trash (ADR-0035): back where it was, with its names and URLs, or
 * under a parent given when that place is gone or in the trash itself. Locks never block it.
 */
final class RestorePage extends Action
{
    use ResolvesPages;

    public function __construct(
        private readonly Trash $trash,
        private readonly BranchMover $mover,
        private readonly PathBuilder $paths,
        private readonly FamilyRules $family,
    ) {}

    public static function name(): string
    {
        return 'pages.restore';
    }

    public static function permission(): string
    {
        return 'pages.delete';
    }

    public static function description(): string
    {
        return 'Take a page and the pages under it out of the trash, back where they were. Give a parent when that place no longer exists or is in the trash.';
    }

    public static function rules(): array
    {
        return ['page' => ['required'], 'parent' => ['sometimes', 'nullable']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id of the trashed page (the top of a trashed branch).')->required(),
            'parent' => $schema->string()->description('Id or path of a new parent. Omit to restore the page where it was.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $record = $this->trash->record((int) $page->id) ?? throw ValidationException::withMessages(['page' => $this->notTrashed($page)]);
        $parent = $input->get('parent') === null ? $this->originalParent($record['parent']) : $this->newParent($input->get('parent'), $page);
        $isBackHome = $parent->id === $record['parent'];

        $context->authorize('pages.delete', $page);

        if (! $isBackHome) {
            $context->authorize('pages.move', $parent);
        }

        $this->assertNamesAreFree($record['names'], (int) $page->id, (int) $parent->id);

        foreach ($record['names'] as $languageId => $name) {
            DB::table(Tables::PAGE_LANGUAGES)->where('page_id', $page->id)->where('language_id', $languageId)->update(['name' => $name]);
        }

        $this->mover->move($page, (int) $parent->id, $isBackHome ? $record['sort'] : PHP_INT_MAX);
        $this->trash->forget([(int) $page->id]);
        $this->paths->rebuild((int) $page->id, descendants: true, changes: $context->changes);

        $context->changes->updated('page', $page->id, "Restore page {$page->name()} under {$parent->name()}", ['to' => $parent->id]);
        $context->event(new PageRestored((int) $page->id, (int) $parent->id));

        return $page;
    }

    private function notTrashed(Page $page): string
    {
        return $this->trash->contains((int) $page->id)
            ? "Page [{$page->name()}] is inside a trashed branch; restore the top page of that branch."
            : "Page [{$page->name()}] is not in the trash.";
    }

    private function originalParent(int $parentId): Page
    {
        $parent = lynx()->pages->get($parentId);

        if (! $parent instanceof Page) {
            throw ValidationException::withMessages(['parent' => 'Choose a new parent: the page this one was under no longer exists.']);
        }

        if ($this->trash->contains($parentId)) {
            throw ValidationException::withMessages(['parent' => "Choose a new parent: [{$parent->name()}], where this page was, is in the trash."]);
        }

        return $parent;
    }

    private function newParent(mixed $reference, Page $page): Page
    {
        $parent = $this->resolvePageReference($reference, 'parent');

        if ($parent->id === $this->trash->rootId() || $this->trash->contains((int) $parent->id)) {
            throw ValidationException::withMessages(['parent' => "Page [{$parent->name()}] is in the trash; choose a parent outside it."]);
        }

        $this->family->assertAllowed($page->template(), (int) $parent->id, 'parent');

        return $parent;
    }

    /**
     * @param  array<int, string>  $names  by language id
     */
    private function assertNamesAreFree(array $names, int $pageId, int $parentId): void
    {
        foreach ($names as $languageId => $name) {
            $isTaken = $name !== '' && DB::table(Tables::PAGE_LANGUAGES)->where('parent_id', $parentId)
                ->where('language_id', $languageId)->where('name', $name)->where('page_id', '!=', $pageId)->exists();

            if ($isTaken) {
                throw ValidationException::withMessages(['parent' => "A page named [{$name}] is already there; choose another parent."]);
            }
        }
    }
}
