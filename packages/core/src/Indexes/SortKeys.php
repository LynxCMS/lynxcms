<?php

declare(strict_types=1);

namespace LynxCms\Core\Indexes;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Setting;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Sort keys (ADR-0061): for the translatable fields named in lynx.sort_keys, the value each page
 * shows in each language, fallback included, so sorting reads an index instead of sorting a
 * COALESCE. Derived data, like the search index. Every page has a row per field and language
 * (null when nothing shows), so sorting joins the keys and the database can read them in order;
 * a field sorts through its keys only once a full rebuild has written them.
 *
 * @internal
 */
final readonly class SortKeys
{
    public const int MAX_LENGTH = 191;

    private const int CHUNK = 200;

    public function __construct(private FieldRegistry $fields, private LanguageRegistry $languages) {}

    /** Setting that lists the fields whose keys a full rebuild wrote. */
    public const string BUILT_SETTING = 'sort_keys.fields';

    /** Whether sorting by the field reads sort keys: it is configured and its keys are written. */
    public function covers(Field $field): bool
    {
        return $this->isConfigured($field) && in_array($field->name, $this->built(), true);
    }

    /**
     * Joins the field's keys in the language and returns the column to order by.
     */
    public function join(Builder $query, Field $field, int $languageId, string $alias): string
    {
        $query->join(Tables::SORT_KEYS." as {$alias}", function (JoinClause $join) use ($alias, $field, $languageId): void {
            $join->on("{$alias}.page_id", '=', Tables::PAGES.'.id')
                ->where("{$alias}.field_id", '=', $field->id)
                ->where("{$alias}.language_id", '=', $languageId);
        });

        return "{$alias}.value";
    }

    public function update(int $pageId): void
    {
        $this->write([$pageId]);
    }

    /**
     * @param  list<int>  $pageIds
     */
    public function remove(array $pageIds): void
    {
        DB::table(Tables::SORT_KEYS)->whereIn('page_id', $pageIds)->delete();
    }

    /** Writes the keys of every page again, or only those on a template. Returns how many pages. */
    public function rebuild(?int $templateId = null): int
    {
        if ($templateId === null) {
            DB::table(Tables::SORT_KEYS)->delete();
            Setting::write(self::BUILT_SETTING, null);
        }

        $count = 0;
        DB::table(Tables::PAGES)->whereNull('draft_of')->when($templateId !== null, fn (Builder $q) => $q->where('template_id', $templateId))
            ->select('id')->chunkById(self::CHUNK, function ($rows) use (&$count): void {
                $ids = Value::ints($rows->pluck('id')->all());
                $this->write($ids);
                $count += count($ids);
            });

        if ($templateId === null) {
            Setting::write(self::BUILT_SETTING, json_encode(array_map(fn (Field $field): string => $field->name, $this->configuredFields()), JSON_THROW_ON_ERROR));
        }

        return $count;
    }

    /**
     * @param  list<int>  $pageIds
     */
    private function write(array $pageIds): void
    {
        $this->remove($pageIds);
        $pageIds = Value::ints(DB::table(Tables::PAGES)->whereIn('id', $pageIds)->whereNull('draft_of')->pluck('id')->all());
        $rows = [];

        foreach ($this->configuredFields() as $field) {
            $values = $this->values($field, $pageIds);

            foreach ($pageIds as $pageId) {
                foreach ($this->languages->all() as $language) {
                    $value = $this->shown($field, $language, $values[$pageId] ?? []);
                    $rows[] = ['page_id' => $pageId, 'field_id' => $field->id, 'language_id' => $language->id, 'value' => $value === null ? null : mb_substr($value, 0, self::MAX_LENGTH)];
                }
            }
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table(Tables::SORT_KEYS)->insert($chunk);
        }
    }

    private function isConfigured(Field $field): bool
    {
        return $field->isTranslatable() && in_array($field->name, Value::strings(config('lynx.sort_keys', [])), true);
    }

    /** @return list<string> the fields a full rebuild wrote keys for */
    private function built(): array
    {
        return Value::strings(json_decode(Setting::read(self::BUILT_SETTING) ?? '[]', true));
    }

    /** @return list<Field> */
    private function configuredFields(): array
    {
        return array_values(array_filter(array_map($this->fields->find(...), Value::strings(config('lynx.sort_keys', []))), fn (?Field $field): bool => $field instanceof Field && $this->isConfigured($field)));
    }

    /**
     * The field's stored values by page and language id.
     *
     * @param  list<int>  $pageIds
     * @return array<int, array<int, string>>
     */
    private function values(Field $field, array $pageIds): array
    {
        $values = [];

        foreach (DB::table($field->table())->whereIn('page_id', $pageIds)->whereNotNull('data')->get(['page_id', 'language_id', 'data']) as $row) {
            $values[Value::int($row->page_id)][Value::int($row->language_id)] = Value::string($row->data);
        }

        return $values;
    }

    /**
     * The value the language shows: its own, else the first along its fallback chain.
     *
     * @param  array<int, string>  $byLanguage
     */
    private function shown(Field $field, Language $language, array $byLanguage): ?string
    {
        $chain = $field->fallbackMode() === 'none' ? [$language->id] : $this->languages->chainIds($language);

        foreach ($chain as $languageId) {
            if (isset($byLanguage[$languageId])) {
                return $byLanguage[$languageId];
            }
        }

        return null;
    }
}
