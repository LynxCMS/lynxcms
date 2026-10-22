<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Strings\PageStringEdits;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Revisions (ADR-0038): a page's values in every language, as Page::valuesByLanguage() gives
 * them ("*" for fields that are not translatable), and the page's own template strings
 * (ADR-0048), stored after each save that changed either.
 *
 * @internal
 */
final readonly class Revisions
{
    public function __construct(
        private PageLoader $loader,
        private TemplateRegistry $templates,
        private LanguageRegistry $languages,
        private PageStringEdits $strings,
    ) {}

    public function record(int $pageId, int $revision, string $by): void
    {
        $this->loader->forget($pageId);
        $page = $this->loader->find($pageId);

        if (! $page instanceof Page) {
            return;
        }

        DB::table(Tables::REVISIONS)->insert([
            'page_id' => $pageId,
            'revision' => $revision,
            'values' => json_encode($page->valuesByLanguage(), JSON_THROW_ON_ERROR),
            'strings' => json_encode($this->strings->snapshot($pageId), JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT),
            'created_by' => $by,
            'created_at' => now(),
        ]);

        $this->prune($pageId);
    }

    /**
     * Newest first.
     *
     * @return list<array{id: int, revision: int, created_by: string|null, created_at: CarbonImmutable}>
     */
    public function of(int $pageId): array
    {
        $list = [];

        foreach (DB::table(Tables::REVISIONS)->where('page_id', $pageId)->orderByDesc('id')->get(['id', 'revision', 'created_by', 'created_at']) as $row) {
            $list[] = [
                'id' => Value::int($row->id),
                'revision' => Value::int($row->revision),
                'created_by' => Value::nullableString($row->created_by),
                'created_at' => CarbonImmutable::parse(Value::string($row->created_at)),
            ];
        }

        return $list;
    }

    /**
     * Strings are null for revisions recorded before they kept strings.
     *
     * @return array{page: int, values: array<string, array<string, mixed>>, strings: array<string, array<string, string>>|null}|null values by language code ("*" for untranslated)
     */
    public function find(int $id): ?array
    {
        $row = DB::table(Tables::REVISIONS)->where('id', $id)->first(['page_id', 'values', 'strings']);

        if ($row === null) {
            return null;
        }

        $values = [];

        foreach (Value::map(json_decode(Value::string($row->values), true)) as $code => $fields) {
            $values[(string) $code] = Value::map($fields);
        }

        return ['page' => Value::int($row->page_id), 'values' => $values, 'strings' => $this->storedStrings($row->strings)];
    }

    /**
     * Stored values as pages.save takes them ({"code": value} for translatable fields), for the
     * fields the page's template has now; values the revision did not have become null.
     *
     * @param  array<string, array<string, mixed>>  $stored  by language code ("*" for untranslated)
     * @return array<string, mixed>
     */
    public function saveInput(Page $page, array $stored): array
    {
        $values = [];

        foreach ($this->templates->fields($page->templateId) as $name => $templateField) {
            if (! $templateField->field->isTranslatable()) {
                $values[$name] = $stored['*'][$name] ?? null;

                continue;
            }

            $byLanguage = [];

            foreach ($this->languages->all() as $language) {
                $byLanguage[(string) $language->code] = $stored[(string) $language->code][$name] ?? null;
            }

            $values[$name] = $byLanguage;
        }

        return $values;
    }

    /**
     * @return array<string, array<string, string>>|null
     */
    private function storedStrings(mixed $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $strings = [];

        foreach (Value::map(json_decode(Value::string($json), true)) as $code => $texts) {
            $strings[(string) $code] = array_map(Value::string(...), Value::map($texts));
        }

        return $strings;
    }

    private function prune(int $pageId): void
    {
        $keep = max(1, Value::int(config('lynx.revisions.keep'), 50));
        $oldest = DB::table(Tables::REVISIONS)->where('page_id', $pageId)->orderByDesc('id')->skip($keep - 1)->value('id');

        if ($oldest !== null) {
            DB::table(Tables::REVISIONS)->where('page_id', $pageId)->where('id', '<', Value::int($oldest))->delete();
        }
    }
}
