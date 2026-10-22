<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Machine translation of item fields (ADR-0044): the translatable text subfields of each item,
 * keyed "{field}.{item key}.{subfield}" for the provider. Item values keep no origin, so only
 * missing translations are filled unless asked to overwrite.
 *
 * @internal
 */
final class ItemTranslations
{
    /**
     * @return array{0: array<string, string>, 1: array<string, string>} texts and formats by key
     */
    public function collect(Page $page, Field $field, Language $from, Language $to, bool $overwrite): array
    {
        $type = $field->fieldType();
        $texts = [];
        $formats = [];

        if (! $type instanceof ItemFieldType) {
            return [[], []];
        }

        foreach (Value::list($page->getUnformatted($field->name)) as $item) {
            $item = Value::array($item);
            $values = Value::map($item['values'] ?? []);

            foreach ($type->subfields($field->config(), Value::nullableString($item['type'] ?? null)) as $subfield) {
                $map = Value::map($values[$subfield->name()] ?? []);
                $source = $map[(string) $from->code] ?? null;

                if (! $subfield->isTranslatable() || ! is_string($source) || trim($source) === '' || (isset($map[(string) $to->code]) && ! $overwrite)) {
                    continue;
                }

                $key = "{$field->name}.".Value::string($item['key'] ?? '').".{$subfield->name()}";
                $texts[$key] = $source;
                $formats[$key] = Value::string($subfield->field->config()['format'] ?? 'plain');
            }
        }

        return [$texts, $formats];
    }

    /**
     * Put translations back into the page's item values.
     *
     * @param  array<string, string>  $translated  keyed as collect() keys them
     */
    public function apply(Page $page, Field $field, Language $to, array $translated): void
    {
        $items = Value::list($page->getUnformatted($field->name));
        $changed = false;

        foreach ($items as $index => $item) {
            $item = Value::array($item);
            $values = Value::map($item['values'] ?? []);

            foreach ($values as $name => $value) {
                $key = "{$field->name}.".Value::string($item['key'] ?? '').".{$name}";

                if (isset($translated[$key])) {
                    $map = Value::map($value);
                    $map[(string) $to->code] = $translated[$key];
                    $values[$name] = $map;
                    $changed = true;
                }
            }

            $items[$index] = ['values' => $values] + $item;
        }

        if ($changed) {
            $page->setLanguageValue($to, $field->name, $items);
        }
    }
}
