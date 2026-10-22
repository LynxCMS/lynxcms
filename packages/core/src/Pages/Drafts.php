<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Strings\PageStringEdits;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Drafts (ADR-0039): a page row marked draft_of a live page, hidden and unpublished, with no
 * parent, names or paths, holding the next version of the live page's values in the same field
 * tables, and of its own template strings (ADR-0048). One per page.
 *
 * @internal
 */
final readonly class Drafts
{
    public function __construct(private FieldRegistry $fields, private PageStringEdits $strings) {}

    public function of(int $pageId): ?int
    {
        $id = DB::table(Tables::PAGES)->where('draft_of', $pageId)->value('id');

        return $id === null ? null : Value::int($id);
    }

    /**
     * Which of the pages have a draft, in one query.
     *
     * @param  list<int>  $pageIds
     * @return list<int>
     */
    public function among(array $pageIds): array
    {
        return $pageIds === [] ? [] : Value::ints(DB::table(Tables::PAGES)->whereIn('draft_of', $pageIds)->pluck('draft_of')->all());
    }

    public function isDraft(int $pageId): bool
    {
        return DB::table(Tables::PAGES)->where('id', $pageId)->whereNotNull('draft_of')->exists();
    }

    /** A new draft of the page, starting from its values in every language. */
    public function create(Page $page, string $by): int
    {
        $now = now();
        $draft = (int) DB::table(Tables::PAGES)->insertGetId([
            'parent_id' => null,
            'template_id' => $page->templateId,
            'status' => PageStatus::HIDDEN | PageStatus::UNPUBLISHED,
            'sort' => 0,
            'draft_of' => $page->id,
            'created_by' => $by,
            'modified_by' => $by,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($this->valueTables() as $table) {
            $rows = array_map(fn (object $row): array => ['page_id' => $draft] + (array) $row, DB::table($table)->where('page_id', $page->id)->get()->all());

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }

        $this->strings->copy((int) $page->id, $draft, $by);

        return $draft;
    }

    public function delete(int $draftId): void
    {
        foreach ($this->valueTables() as $table) {
            DB::table($table)->where('page_id', $draftId)->delete();
        }

        $this->strings->deleteAll($draftId);

        DB::table(Tables::PAGES)->where('id', $draftId)->whereNotNull('draft_of')->delete();
    }

    /**
     * Every table holding values by page: the field tables and the value metadata.
     *
     * @return list<string>
     */
    private function valueTables(): array
    {
        return [...$this->fields->all()->map(fn (Field $field): string => $field->table())->values()->all(), Tables::FIELD_VALUE_META];
    }
}
