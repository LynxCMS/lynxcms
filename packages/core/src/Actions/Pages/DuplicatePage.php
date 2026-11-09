<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Drafts;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageCopy;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Copies a page with its values in every language, its template strings and, when asked, every
 * page below it. The copy goes under the same parent (or another), named as the original or, when
 * a sibling has that name, "name-copy"; it starts unpublished. Every page is created with
 * pages.save, which checks the user's permissions and the template rules for each one.
 */
final class DuplicatePage extends Action
{
    use ResolvesPages;

    public function __construct(
        private readonly PageCopy $copy,
        private readonly PageLoader $loader,
        private readonly Trash $trash,
        private readonly Drafts $drafts,
    ) {}

    public static function name(): string
    {
        return 'pages.duplicate';
    }

    public static function permission(): string
    {
        return 'pages.create';
    }

    public static function description(): string
    {
        return 'Copy a page with its values in every language, unpublished, next to it as "name-copy" or under another parent. With "children" the pages below it are copied too.';
    }

    public static function rules(): array
    {
        return [
            'page' => ['required'],
            'parent' => ['sometimes', 'nullable'],
            'children' => ['sometimes', 'boolean'],
            'title_prefix' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the page to copy.')->required(),
            'parent' => $schema->string()->description('Id or path of the parent for the copy. Default: the page\'s own parent.'),
            'children' => $schema->boolean()->description('Also copy every page below it. Default: false.'),
            'title_prefix' => $schema->string()->description('Text put before the copy\'s title in every language, e.g. "[Copy]". The pages below keep their titles.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $this->assertCopyable($page);
        $parent = $input->get('parent') === null ? (int) $page->parentId : (int) $this->resolvePageReference($input->get('parent'), 'parent')->id;
        $this->assertCanGoUnder($page, $parent, $input->bool('children'));

        /** @var array<int, int> $copies original id => copy id */
        $copies = [];
        $pages = [];
        $copyId = $this->copyBranch($page, $parent, $input->bool('children'), $context, $copies, $pages, $input->string('title_prefix'));

        foreach ($pages as $original) {
            $values = $this->copy->finish($original, $copies[(int) $original->id], $copies, $context->dryRun);

            if ($values !== []) {
                $context->run(SavePage::class, ['id' => $copies[(int) $original->id], 'values' => $values]);
            }
        }

        $context->changes->created('page', $copyId, sprintf('Duplicate page %s (%d page(s))', $page->name(), count($copies)), ['from' => $page->id]);

        return $this->loader->find($copyId) ?? $page;
    }

    /**
     * Creates the copy of $page under $parentId, then of each child when asked.
     *
     * @param  array<int, int>  $copies
     * @param  list<Page>  $pages  the originals copied, for the second save
     * @param  string|null  $titlePrefix  for the top page only
     */
    private function copyBranch(Page $page, int $parentId, bool $withChildren, ActionContext $context, array &$copies, array &$pages, ?string $titlePrefix = null): int
    {
        $created = $context->run(SavePage::class, $this->copy->create($page, $parentId, isUnpublished: $copies === [], titlePrefix: $titlePrefix));
        $copyId = $created instanceof Page ? (int) $created->id : Value::int($created);
        $copies[(int) $page->id] = $copyId;
        $pages[] = $page;

        if ($withChildren) {
            foreach ($page->children('include=all') as $child) {
                $this->copyBranch($child, $copyId, true, $context, $copies, $pages);
            }
        }

        return $copyId;
    }

    private function assertCopyable(Page $page): void
    {
        if ($page->parentId === null) {
            throw ValidationException::withMessages(['page' => 'The home page cannot be duplicated.']);
        }

        if ($this->trash->contains((int) $page->id)) {
            throw ValidationException::withMessages(['page' => "Page [{$page->name()}] is in the trash. Take it out with pages.restore first."]);
        }

        if ($this->drafts->isDraft((int) $page->id)) {
            throw ValidationException::withMessages(['page' => 'Duplicate the live page, not its draft.']);
        }
    }

    private function assertCanGoUnder(Page $page, int $parentId, bool $withChildren): void
    {
        if ($this->trash->holds($parentId)) {
            throw ValidationException::withMessages(['parent' => 'A copy cannot go to the trash.']);
        }

        // The branch would grow while it is copied: each copy of the page would be copied again.
        $isInBranch = DB::table(Tables::PAGE_CLOSURE)->where('ancestor_id', $page->id)->where('descendant_id', $parentId)->exists();

        if ($withChildren && $isInBranch) {
            throw ValidationException::withMessages(['parent' => 'A page cannot be copied with its children under itself or one of them.']);
        }
    }
}
