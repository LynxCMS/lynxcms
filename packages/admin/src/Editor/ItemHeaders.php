<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Fields\Types\MatrixType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Headers of the items in a repeater or matrix input: the field's (or matrix type's) item_label
 * with {subfield} placeholders filled from the form, page references as titles loaded in one query.
 * Without an item_label: the type label (matrix) or "#n", then the first text value.
 *
 * @internal
 */
final class ItemHeaders
{
    /**
     * @param  list<mixed>  $items  form items
     * @return list<string>
     */
    public function for(Field $field, array $items, string $code): array
    {
        $type = $field->fieldType();

        if (! $type instanceof ItemFieldType) {
            return [];
        }

        $titles = $this->titles($field, $type, $items);
        $headers = [];

        foreach ($items as $index => $item) {
            $item = Value::array($item);
            $itemType = Value::nullableString($item['type'] ?? null);
            $values = $this->texts($field, $type, $item, $code, $titles);
            $template = $this->template($field, $type, $itemType);
            $label = $template === ''
                ? trim(($type instanceof MatrixType ? $type->typeLabel($field->config(), (string) $itemType) : '#'.($index + 1)).' '.(array_first(array_filter($values)) ?? ''))
                : trim(preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', fn (array $m): string => $values[$m[1]] ?? '', $template) ?? '', ' ·-–|');
            $headers[] = $label === '' ? '#'.($index + 1) : $label;
        }

        return $headers;
    }

    private function template(Field $field, ItemFieldType $type, ?string $itemType): string
    {
        if ($type instanceof MatrixType) {
            foreach (Value::list($field->config()['types'] ?? []) as $definition) {
                if ((Value::array($definition)['slug'] ?? null) === $itemType) {
                    return Value::string(Value::array($definition)['item_label'] ?? '');
                }
            }

            return '';
        }

        return Value::string($field->config()['item_label'] ?? '');
    }

    /**
     * Each subfield as short text for the header.
     *
     * @param  array<array-key, mixed>  $item
     * @param  array<int, string>  $titles
     * @return array<string, string>
     */
    private function texts(Field $field, ItemFieldType $type, array $item, string $code, array $titles): array
    {
        $texts = [];
        $values = Value::map($item['values'] ?? []);

        foreach ($type->subfields($field->config(), Value::nullableString($item['type'] ?? null)) as $subfield) {
            $languages = Value::map($values[$subfield->name()] ?? []);
            $value = $subfield->isTranslatable() ? ($languages[$code] ?? null) : (array_first($languages) ?? null);

            $texts[$subfield->name()] = match (true) {
                $subfield->type() instanceof ReferencesPages => implode(', ', array_filter(array_map(fn (int $id): ?string => $titles[$id] ?? null, Value::ints(Value::array($value))))),
                is_scalar($value) => mb_strimwidth(trim(strip_tags((string) $value)), 0, 60, '…'),
                default => '',
            };
        }

        return $texts;
    }

    /**
     * Titles of every page the items reference.
     *
     * @param  list<mixed>  $items
     * @return array<int, string>
     */
    private function titles(Field $field, ItemFieldType $type, array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $item = Value::array($item);

            foreach ($type->subfields($field->config(), Value::nullableString($item['type'] ?? null)) as $subfield) {
                if ($subfield->type() instanceof ReferencesPages) {
                    foreach (Value::map(Value::map($item['values'] ?? [])[$subfield->name()] ?? []) as $value) {
                        array_push($ids, ...Value::ints(Value::array($value)));
                    }
                }
            }
        }

        $titles = [];

        foreach ($ids === [] ? [] : lynx()->pages->find('id='.implode('|', array_unique($ids)).', include=all') as $page) {
            $titles[(int) $page->id] = (new DefaultPresenter)->title($page);
        }

        return $titles;
    }
}
