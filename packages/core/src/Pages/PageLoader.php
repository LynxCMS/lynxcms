<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Hydrates pages in batches: one query for the pages, one for their names, then one per field for
 * the whole set, so a listing costs 2 + fields queries however many pages it has. Only the title,
 * fields set to load with the page and fields asked for ($join) load up front; the others load on
 * first read, still once for the whole batch (ADR-0067). Keeps an identity map for the request.
 *
 * @api
 */
final class PageLoader implements Flushable
{
    /** @var array<int, Page> */
    private array $pages = [];

    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly FieldRegistry $fields,
        private readonly PathResolver $paths,
    ) {}

    public function find(int $id): ?Page
    {
        return $this->load([$id])[$id] ?? null;
    }

    /**
     * Load pages by id, keeping the given order.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $with  page-reference fields whose targets are loaded in the same batch
     * @param  list<string>  $join  fields to load up front besides the title and those set to load
     *                              with the page; ["all"] loads every field
     */
    public function many(array $ids, array $with = [], array $join = []): PageArray
    {
        $loaded = $this->load($ids, $with, $join);

        return new PageArray(array_values(array_filter(array_map(fn (int $id): ?Page => $loaded[$id] ?? null, $ids))));
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $with
     * @param  list<string>  $join
     * @return array<int, Page>
     */
    public function load(array $ids, array $with = [], array $join = []): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        $missing = array_values(array_diff($ids, array_keys($this->pages)));

        if ($missing !== []) {
            $this->hydrate($missing, [...$join, ...$with]);
        }

        if (count($ids) > 1) {
            $this->paths->queue($ids);
        }

        if ($with !== []) {
            $this->eagerLoad($ids, $with);
        }

        return array_intersect_key($this->pages, array_flip($ids));
    }

    /**
     * Values of one field for these pages: page id => language id => value. Used by DeferredFields.
     *
     * @param  list<int>  $pageIds
     * @return array<int, array<int, mixed>>
     *
     * @internal
     */
    public function fieldValues(string $name, array $pageIds): array
    {
        $field = $this->fields->find($name);
        $values = [];

        if ($field instanceof Field && $pageIds !== []) {
            $this->loadField($field, $pageIds, $values);
        }

        return array_map(fn (array $byField): array => $byField[$name] ?? [], $values);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $join
     */
    private function hydrate(array $ids, array $join): void
    {
        $rows = DB::table(Tables::PAGES)->whereIn('id', $ids)->get()->keyBy('id');

        if ($rows->isEmpty()) {
            return;
        }

        $names = [];

        foreach (DB::table(Tables::PAGE_LANGUAGES)->whereIn('page_id', $rows->keys())->get() as $row) {
            $names[Value::int($row->page_id)][Value::int($row->language_id)] = ['name' => Value::string($row->name), 'status' => Value::string($row->status)];
        }

        // Group pages by field so each field table is queried once for the whole batch.
        $pagesByField = [];
        $deferred = [];
        $joinAll = in_array('all', $join, true);

        foreach ($rows as $row) {
            foreach ($this->templates->fields(Value::int($row->template_id)) as $name => $templateField) {
                if ($joinAll || in_array($name, $join, true) || $templateField->field->loadsWithPage()) {
                    $pagesByField[$name][] = Value::int($row->id);
                } else {
                    $deferred[Value::int($row->id)][] = $name;
                }
            }
        }

        $batch = new DeferredFields($this);

        $values = [];

        foreach ($pagesByField as $name => $pageIds) {
            $field = $this->fields->get($name);
            $this->loadField($field, $pageIds, $values);
        }

        foreach ($rows as $row) {
            $id = Value::int($row->id);
            $page = new Page(Value::int($row->template_id), Value::nullableInt($row->parent_id));
            $page->id = $id;
            $page->status = Value::int($row->status);
            $page->sort = Value::int($row->sort);
            $page->createdAt = Value::nullableString($row->created_at);
            $page->updatedAt = Value::nullableString($row->updated_at);
            $page->revision = Value::int($row->revision ?? 0);
            $page->publishedAt = Value::nullableString($row->published_at);
            $page->createdBy = Value::nullableString($row->created_by);
            $page->modifiedBy = Value::nullableString($row->modified_by);
            $page->hydrate($values[$id] ?? [], $names[$id] ?? []);
            $page->defer($batch, $deferred[$id] ?? []);
            $batch->add($page);
            $this->pages[$id] = $page;
        }
    }

    /**
     * @param  list<int>  $pageIds
     * @param  array<int, array<string, array<int, mixed>>>  $values
     */
    private function loadField(Field $field, array $pageIds, array &$values): void
    {
        $type = $field->fieldType();
        $query = DB::table($field->table())->whereIn('page_id', $pageIds);

        if ($type->multiple()) {
            $query->orderBy('sort');
        }

        $grouped = [];

        foreach ($query->get() as $row) {
            $language = $field->isTranslatable() ? Value::int($row->language_id) : 0;
            $grouped[Value::int($row->page_id)][$language][] = $row;
        }

        foreach ($grouped as $pageId => $byLanguage) {
            foreach ($byLanguage as $language => $rows) {
                $values[$pageId][$field->name][$language] = $type->wake($rows, $field->config());
            }
        }
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $with
     */
    private function eagerLoad(array $ids, array $with): void
    {
        $targets = [];

        foreach ($ids as $id) {
            $page = $this->pages[$id] ?? null;

            foreach ($with as $field) {
                array_push($targets, ...$this->referencedIds($page?->rawValuesOf($field) ?? [], $field));
            }
        }

        if ($targets !== []) {
            $this->load($targets);
        }
    }

    /**
     * Page ids a field's stored values (every language) point at, when its type references pages.
     *
     * @param  array<int, mixed>  $values  language id => value
     * @return list<int>
     */
    private function referencedIds(array $values, string $field): array
    {
        $definition = $this->fields->find($field);
        $type = $definition?->fieldType();

        if (! $type instanceof ReferencesPages || $definition === null) {
            return [];
        }

        $ids = [];

        foreach ($values as $value) {
            array_push($ids, ...$type->pageIds($value, $definition->config()));
        }

        return $ids;
    }

    public function forget(int $id): void
    {
        unset($this->pages[$id]);
    }

    public function flush(): void
    {
        $this->pages = [];
    }
}
