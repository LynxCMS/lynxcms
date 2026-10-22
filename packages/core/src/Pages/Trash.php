<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;
use UnexpectedValueException;

/**
 * The trash (ADR-0035): a system page /_trash/ under the home page, unpublished and hidden, so
 * nothing in it reaches visitors, and one lynx_trash row per trashed branch saying where it was.
 *
 * @internal
 */
final class Trash implements Flushable
{
    public const string TEMPLATE = 'trash';

    public const string NAME = '_trash';

    private ?int $rootId = null;

    private bool $isRootLoaded = false;

    public function __construct(private readonly TemplateRegistry $templates, private readonly RootPage $root) {}

    /** The trash page's id, creating it (and its template) the first time. */
    public function ensureRoot(ActionContext $context): int
    {
        if ($this->rootId() !== null) {
            return $this->rootId();
        }

        if ($this->templates->find(self::TEMPLATE) === null) {
            $context->run(CreateTemplate::class, ['name' => self::TEMPLATE, 'label' => 'Trash', 'fields' => ['title'], 'has_url' => false, 'singleton' => true]);
        }

        $page = $context->run(SavePage::class, [
            'template' => self::TEMPLATE,
            'parent' => (int) $this->root->id(),
            'names' => [(string) app(LanguageRegistry::class)->default()->code => self::NAME],
            'values' => ['title' => 'Trash'],
            'status' => ['hidden', 'unpublished', 'system'],
        ]);

        $this->rootId = $page instanceof Page ? (int) $page->id : throw new UnexpectedValueException('pages.save did not return the trash page.');
        $this->isRootLoaded = true;

        return $this->rootId;
    }

    public function rootId(): ?int
    {
        if (! $this->isRootLoaded) {
            $id = DB::table(Tables::PAGES)
                ->join(Tables::TEMPLATES, Tables::TEMPLATES.'.id', '=', Tables::PAGES.'.template_id')
                ->where(Tables::TEMPLATES.'.name', self::TEMPLATE)
                ->value(Tables::PAGES.'.id');
            $this->rootId = $id === null ? null : Value::int($id);
            $this->isRootLoaded = true;
        }

        return $this->rootId;
    }

    /** Whether the page is the trash page or in the trash. */
    public function holds(int $pageId): bool
    {
        return $pageId === $this->rootId() || $this->contains($pageId);
    }

    /** Whether the page is in the trash (below it, not the trash page itself). */
    public function contains(int $pageId): bool
    {
        $root = $this->rootId();

        return $root !== null && $pageId !== $root
            && DB::table(Tables::PAGE_CLOSURE)->where('ancestor_id', $root)->where('descendant_id', $pageId)->exists();
    }

    /**
     * Where a trashed branch came from, or null when the page is not the top of one.
     *
     * @return array{parent: int, sort: int, names: array<int, string>}|null names by language id
     */
    public function record(int $pageId): ?array
    {
        $row = DB::table(Tables::TRASH)->where('page_id', $pageId)->first();

        if ($row === null) {
            return null;
        }

        $names = [];

        foreach (Value::map(json_decode(Value::string($row->names), true)) as $languageId => $name) {
            $names[(int) $languageId] = Value::string($name);
        }

        return ['parent' => Value::int($row->parent_id), 'sort' => Value::int($row->sort), 'names' => $names];
    }

    /**
     * The ancestors, at the place it was trashed from, of the trashed branch among these page ids
     * (a page's ancestors), so permissions inside the trash follow that place (ADR-0035).
     *
     * @param  list<int>  $ancestorIds
     * @return list<int>
     */
    public function originalAncestorsAmong(array $ancestorIds): array
    {
        $root = $this->rootId();

        if ($root === null || ! in_array($root, $ancestorIds, true)) {
            return [];
        }

        $ancestors = DB::table(Tables::TRASH)->whereIn('page_id', $ancestorIds)->value('ancestors');

        return $ancestors === null ? [] : Value::ints(json_decode(Value::string($ancestors), true));
    }

    /**
     * Ids of the branches in the trash, oldest first.
     *
     * @return list<int>
     */
    public function branches(): array
    {
        return Value::ints(DB::table(Tables::TRASH)->orderBy('trashed_at')->orderBy('page_id')->pluck('page_id')->all());
    }

    /**
     * @param  array<int, string>  $names  names at the original place, by language id
     * @param  list<int>  $ancestors  the original parent and the pages above it
     */
    public function remember(int $pageId, int $parentId, int $sort, array $names, array $ancestors, ?string $trashedBy): void
    {
        DB::table(Tables::TRASH)->insert([
            'page_id' => $pageId,
            'parent_id' => $parentId,
            'sort' => $sort,
            'names' => json_encode($names, JSON_THROW_ON_ERROR),
            'ancestors' => json_encode($ancestors, JSON_THROW_ON_ERROR),
            'trashed_by' => $trashedBy,
            'trashed_at' => now(),
        ]);
    }

    /**
     * @param  list<int>  $pageIds
     */
    public function forget(array $pageIds): void
    {
        DB::table(Tables::TRASH)->whereIn('page_id', $pageIds)->delete();
    }

    public function flush(): void
    {
        $this->rootId = null;
        $this->isRootLoaded = false;
    }
}
