<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\References;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * The page references index (ADR-0145): one row per reference a page's fields hold. Actions that
 * change stored values or the fields a template uses sync the pages they touch; a rebuild compares
 * every page.
 *
 * @internal
 */
final readonly class PageReferenceIndex
{
    /** Pages compared per query. */
    private const int CHUNK = 500;

    public function __construct(private ReferenceScanner $scanner, private FieldRegistry $fields) {}

    /**
     * Bring the pages' rows in line with their stored values; pages that no longer exist lose theirs.
     *
     * @param  list<int>  $pageIds
     * @return array<int, ReferenceChanges> page id => what changed, for pages whose rows changed
     */
    public function sync(array $pageIds, bool $write = true): array
    {
        $changed = [];

        foreach (array_chunk(array_values(array_unique($pageIds)), self::CHUNK) as $chunk) {
            foreach ($this->compare($chunk) as $pageId => $changes) {
                if ($write) {
                    $this->apply($changes, now()->toDateTimeString());
                }

                $changed[$pageId] = $changes;
            }
        }

        return $changed;
    }

    /**
     * Sync the pages of a template after fields were added to it or removed from it; nothing to do
     * unless one of them references pages.
     *
     * @param  list<string>  $fieldNames  the fields added or removed
     */
    public function syncTemplate(int $templateId, array $fieldNames): void
    {
        foreach ($fieldNames as $name) {
            if ($this->fields->find($name)?->fieldType() instanceof ReferencesPages) {
                $this->sync(Value::ints(DB::table(Tables::PAGES)->where('template_id', $templateId)->whereNull('draft_of')->pluck('id')->all()));

                return;
            }
        }
    }

    /**
     * How every page's rows differ from its stored values.
     *
     * @return array<int, ReferenceChanges>
     */
    public function differences(): array
    {
        $differences = [];

        foreach (array_chunk($this->indexedPageIds(), self::CHUNK) as $chunk) {
            $differences += $this->compare($chunk);
        }

        return $differences;
    }

    /**
     * Compare every page and, unless $dryRun, fix the rows that differ. Rows it adds have no
     * created_at, which means unknown.
     *
     * @return array{pages: int, added: int, removed: int, resorted: int}
     */
    public function rebuild(bool $dryRun = false): array
    {
        $totals = ['pages' => 0, 'added' => 0, 'removed' => 0, 'resorted' => 0];

        foreach (array_chunk($this->indexedPageIds(), self::CHUNK) as $chunk) {
            foreach ($this->compare($chunk) as $changes) {
                if (! $dryRun) {
                    $this->apply($changes, null);
                }

                $totals['pages']++;
                $totals['added'] += count($changes->added);
                $totals['removed'] += count($changes->removed);
                $totals['resorted'] += count($changes->resorted);
            }
        }

        return $totals;
    }

    /**
     * The references as a save's change and PageReferencesChanged list them.
     *
     * @param  iterable<PageReference>  $references
     * @return list<array{linked_page_id: int, field: string, item_key: string|null}>
     */
    public function entries(iterable $references): array
    {
        $entries = [];

        foreach ($references as $reference) {
            $entries[] = [
                'linked_page_id' => $reference->linkedPageId,
                'field' => (string) $this->fields->find($reference->fieldId)?->name,
                'item_key' => $reference->itemKey,
            ];
        }

        return $entries;
    }

    /**
     * What a save's change and PageReferencesChanged list: empty when no reference was added or removed.
     *
     * @return array{}|array{added: list<array{linked_page_id: int, field: string, item_key: string|null}>, removed: list<array{linked_page_id: int, field: string, item_key: string|null}>}
     */
    public function summary(?ReferenceChanges $changes): array
    {
        return $changes?->changesReferences() === true ? ['added' => $this->entries($changes->added), 'removed' => $this->entries($changes->removed)] : [];
    }

    /**
     * @param  list<int>  $pageIds
     * @return array<int, ReferenceChanges> only pages whose rows differ
     */
    private function compare(array $pageIds): array
    {
        $stored = DB::table(Tables::PAGE_REFERENCES)->whereIn('page_id', $pageIds)->orderBy('id')->get()->groupBy('page_id');
        $differences = [];

        foreach ($this->scanner->scan($pageIds) as $pageId => $expected) {
            $changes = ReferenceChanges::between($expected, $stored->get($pageId) ?? []);

            if (! $changes->isEmpty()) {
                $differences[$pageId] = $changes;
            }
        }

        return $differences;
    }

    private function apply(ReferenceChanges $changes, ?string $createdAt): void
    {
        if ($changes->removed !== []) {
            DB::table(Tables::PAGE_REFERENCES)->whereIn('id', array_keys($changes->removed))->delete();
        }

        foreach ($changes->resorted as $id => $sort) {
            DB::table(Tables::PAGE_REFERENCES)->where('id', $id)->update(['sort' => $sort]);
        }

        if ($changes->added !== []) {
            DB::table(Tables::PAGE_REFERENCES)->insert(array_map(fn (PageReference $reference): array => $reference->toRow($createdAt), $changes->added));
        }
    }

    /**
     * Every page that holds references or has rows: live pages and pages the index still lists.
     *
     * @return list<int>
     */
    private function indexedPageIds(): array
    {
        $ids = [
            ...Value::ints(DB::table(Tables::PAGES)->whereNull('draft_of')->pluck('id')->all()),
            ...Value::ints(DB::table(Tables::PAGE_REFERENCES)->distinct()->pluck('page_id')->all()),
        ];

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
