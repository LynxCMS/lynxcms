<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor\Converters;

use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Admin\Editor\PreparesDisplay;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Repeater and matrix values in the editor (ADR-0044): each item as {key, type, enabled, depth,
 * values: {subfield: {language code: form value}}}, every language at once, each subfield value
 * converted by its own type's converter. Values of fields the item does not show are not in the
 * form; pages.save merges and keeps them.
 *
 * @internal
 */
final readonly class ItemsConverter implements FormConverter, PreparesDisplay
{
    public function __construct(private FieldInputs $inputs) {}

    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        $type = $field?->fieldType();

        if (! $type instanceof ItemFieldType || $field === null) {
            return [];
        }

        $items = [];

        foreach (Value::list($value) as $item) {
            $item = Value::array($item);
            $itemType = Value::nullableString($item['type'] ?? null);
            $stored = Value::map($item['values'] ?? []);
            $values = [];

            foreach ($type->subfields($field->config(), $itemType) as $subfield) {
                $values[$subfield->name()] = $this->subfieldToForm($subfield, $stored[$subfield->name()] ?? null);
            }

            $items[] = [
                'key' => Value::string($item['key'] ?? ''),
                'type' => $itemType,
                'enabled' => (bool) ($item['enabled'] ?? true),
                'depth' => Value::int($item['depth'] ?? 0),
                'values' => $values,
            ];
        }

        return $items;
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        $type = $field?->fieldType();

        if (! $type instanceof ItemFieldType || $field === null) {
            return [];
        }

        $items = [];

        foreach (Value::list($value) as $item) {
            $item = Value::array($item);
            $itemType = Value::nullableString($item['type'] ?? null);
            $form = Value::map($item['values'] ?? []);
            $values = [];

            // Every shown subfield is sent, null or {code: null} when cleared: pages.save merges
            // items into the stored ones, so a missing value would keep the old one.
            foreach ($type->subfields($field->config(), $itemType) as $subfield) {
                $values[$subfield->name()] = $this->subfieldFromForm($subfield, Value::map($form[$subfield->name()] ?? []));
            }

            $items[] = array_filter([
                'key' => Value::string($item['key'] ?? ''),
                'type' => $itemType,
                'enabled' => (bool) ($item['enabled'] ?? true),
                'depth' => Value::int($item['depth'] ?? 0),
                'values' => $values,
            ], fn (mixed $v): bool => $v !== null && $v !== '');
        }

        return $items;
    }

    /** "3 items, 1 off" for reviews and fallback previews. */
    public function display(mixed $value): mixed
    {
        $items = Value::list($value);
        $off = count(array_filter($items, fn (mixed $item): bool => ! (bool) (Value::array($item)['enabled'] ?? true)));
        $summary = count($items) === 1 ? '1 item' : count($items).' items';

        return $off === 0 ? $summary : "{$summary}, {$off} off";
    }

    /**
     * @return array<string, mixed> language code => form value (the default language only for shared subfields)
     */
    private function subfieldToForm(Subfield $subfield, mixed $stored): array
    {
        $form = [];

        foreach ($this->codes($subfield) as $code) {
            $raw = $subfield->isTranslatable() ? (Value::array($stored)[$code] ?? null) : $stored;
            $form[$code] = $this->convert($subfield, $raw, toForm: true);
        }

        return $form;
    }

    /**
     * A shared value, or every language's value with null for the cleared ones.
     *
     * @param  array<string, mixed>  $form
     */
    private function subfieldFromForm(Subfield $subfield, array $form): mixed
    {
        if (! $subfield->isTranslatable()) {
            return $this->convert($subfield, $form[$this->codes($subfield)[0]] ?? null, toForm: false);
        }

        $map = [];

        foreach ($this->codes($subfield) as $code) {
            $map[$code] = $this->convert($subfield, $form[$code] ?? null, toForm: false);
        }

        return $map;
    }

    /** Through the subfield type's own converter; a null result is kept (empty input falls back). */
    private function convert(Subfield $subfield, mixed $value, bool $toForm): mixed
    {
        $converter = $this->inputs->converter((string) $subfield->field->type);

        if (! $converter instanceof FormConverter) {
            return $value;
        }

        return $toForm ? $converter->toForm($value, $subfield->field) : $converter->fromForm($value, $subfield->field);
    }

    /**
     * @return list<string>
     */
    private function codes(Subfield $subfield): array
    {
        return $subfield->isTranslatable()
            ? Value::strings(lynx()->languages->all()->pluck('code')->all())
            : [Value::string(lynx()->languages->default()->code)];
    }
}
