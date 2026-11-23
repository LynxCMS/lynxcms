<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\References;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * The references pages hold according to their stored values (ADR-0145): what the index should
 * contain. Only fields the page's template uses count, and drafts hold none.
 *
 * @internal
 */
final readonly class ReferenceScanner
{
    public function __construct(
        private TemplateRegistry $templates,
        private FieldRegistry $fields,
        private LanguageRegistry $languages,
    ) {}

    /**
     * @param  list<int>  $pageIds
     * @return array<int, list<PageReference>> every requested page id, with its references
     */
    public function scan(array $pageIds): array
    {
        $found = array_fill_keys($pageIds, []);
        $pages = DB::table(Tables::PAGES)->whereIn('id', $pageIds)->whereNull('draft_of')->get(['id', 'template_id']);

        foreach ($pages->groupBy('template_id') as $templateId => $rows) {
            $ids = Value::ints($rows->pluck('id')->all());

            foreach ($this->referenceFields(Value::int($templateId)) as $field) {
                foreach ($this->values($field, $ids) as $pageId => $byLanguage) {
                    array_push($found[$pageId], ...$this->references($pageId, $field, $byLanguage));
                }
            }
        }

        return $found;
    }

    /**
     * @return list<Field>
     */
    private function referenceFields(int $templateId): array
    {
        if ($this->templates->find($templateId) === null) {
            return [];
        }

        return array_values($this->templates->fields($templateId)
            ->map(fn (TemplateField $templateField): Field => $templateField->field)
            ->filter(fn (Field $field): bool => $field->fieldType() instanceof ReferencesPages)
            ->all());
    }

    /**
     * Stored values per page and language (0 for shared fields), woken as the page loader does.
     *
     * @param  list<int>  $pageIds
     * @return array<int, array<int, mixed>>
     */
    private function values(Field $field, array $pageIds): array
    {
        $type = $field->fieldType();
        $query = DB::table($field->table())->whereIn('page_id', $pageIds);
        $grouped = [];
        $values = [];

        foreach ($type->multiple() ? $query->orderBy('sort')->get() : $query->get() as $row) {
            $grouped[Value::int($row->page_id)][$field->isTranslatable() ? Value::int($row->language_id) : 0][] = $row;
        }

        foreach ($grouped as $pageId => $byLanguage) {
            foreach ($byLanguage as $languageId => $rows) {
                $values[$pageId][$languageId] = $type->wake($rows, $field->config());
            }
        }

        return $values;
    }

    /**
     * @param  array<int, mixed>  $byLanguage
     * @return list<PageReference>
     */
    private function references(int $pageId, Field $field, array $byLanguage): array
    {
        $type = $field->fieldType();

        if ($type instanceof ItemFieldType) {
            return $this->itemReferences($pageId, $field, $byLanguage[0] ?? []);
        }

        if (! $type instanceof ReferencesPages) {
            return [];
        }

        $references = [];

        foreach ($byLanguage as $languageId => $value) {
            $references = [...$references, ...$this->located($pageId, $field, $type, $value, $field->isTranslatable() ? $languageId : null, null, 0)];
        }

        return $references;
    }

    /**
     * References in repeater and matrix items, with the key of the item that holds each, and the
     * language of a translatable subfield's value.
     *
     * @return list<PageReference>
     */
    private function itemReferences(int $pageId, Field $field, mixed $items): array
    {
        $references = [];
        $counts = [];

        foreach (Value::list($items) as $item) {
            $item = Value::array($item);

            foreach (Value::map($item['values'] ?? []) as $name => $subValue) {
                $subfield = $this->fields->find($name);
                $subType = $subfield?->fieldType();

                if ($subfield === null || ! $subType instanceof ReferencesPages) {
                    continue;
                }

                foreach ($this->byLanguage($subfield, $subValue) as [$languageId, $value]) {
                    $located = $this->located($pageId, $field, $subType, $value, $languageId, Value::nullableString($item['key'] ?? null), $counts[$languageId ?? 0] ?? 0, $subfield);
                    $counts[$languageId ?? 0] = ($counts[$languageId ?? 0] ?? 0) + count($located);
                    $references = [...$references, ...$located];
                }
            }
        }

        return $references;
    }

    /**
     * A subfield's value per language: [language id, value], with a null id for shared fields.
     *
     * @return list<array{0: int|null, 1: mixed}>
     */
    private function byLanguage(Field $subfield, mixed $value): array
    {
        if (! $subfield->isTranslatable()) {
            return [[null, $value]];
        }

        $values = [];

        foreach (Value::map($value) as $code => $one) {
            $language = $this->languages->find($code);

            if ($language !== null) {
                $values[] = [Value::int($language->id), $one];
            }
        }

        return $values;
    }

    /**
     * @return list<PageReference>
     */
    private function located(int $pageId, Field $field, ReferencesPages $type, mixed $value, ?int $languageId, ?string $itemKey, int $firstSort, ?Field $valueField = null): array
    {
        $references = [];

        foreach ($type->pageIds($value, ($valueField ?? $field)->config()) as $linkedPageId) {
            if ($linkedPageId > 0) {
                $references[] = new PageReference($pageId, $linkedPageId, Value::int($field->id), $languageId, $itemKey, $firstSort + count($references));
            }
        }

        return $references;
    }
}
