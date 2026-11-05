<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use LynxCms\Core\Fields\HasTranslatableTexts;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Machine translation of item fields (ADR-0044): the translatable text subfields of each item,
 * keyed "{field}.{item key}.{subfield}" for the provider, its translatable properties (a
 * picture's description), and the texts inside subfields whose type holds them per language
 * (HasTranslatableTexts, keyed "{field}.{item key}.{subfield}.{text key}", ADR-0105). Item values
 * keep no origin, so only missing translations are filled; overwrite reaches the text subfields
 * and properties only.
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

            $prefix = "{$field->name}.".Value::string($item['key'] ?? '').'.';

            foreach ($type->subfields($field->config(), Value::nullableString($item['type'] ?? null)) as $subfield) {
                $subType = $subfield->field->fieldType();

                if ($subType instanceof HasTranslatableTexts) {
                    foreach ($subType->untranslatedTexts($values[$subfield->name()] ?? null, $from, $to) as $textKey => $text) {
                        $texts[$prefix.$subfield->name().'.'.$textKey] = $text;
                        $formats[$prefix.$subfield->name().'.'.$textKey] = 'plain';
                    }
                } elseif ($subfield->isTranslatable() && ($source = $this->source($values[$subfield->name()] ?? null, $from, $to, $overwrite)) !== null) {
                    $texts[$prefix.$subfield->name()] = $source;
                    $formats[$prefix.$subfield->name()] = Value::string($subfield->field->config()['format'] ?? 'plain');
                }
            }

            foreach ($this->translatableProperties($type) as $property) {
                if (($source = $this->source($values[$property] ?? null, $from, $to, $overwrite)) !== null) {
                    $texts[$prefix.$property] = $source;
                    $formats[$prefix.$property] = 'plain';
                }
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

            $prefix = "{$field->name}.".Value::string($item['key'] ?? '').'.';

            foreach ($values as $name => $value) {
                $parts = $this->parts($translated, $prefix.$name.'.');
                $subType = $parts === [] ? null : $this->subfieldType($field, $item, (string) $name);

                if ($subType instanceof HasTranslatableTexts) {
                    $values[$name] = $subType->withTranslatedTexts($value, $to, $parts);
                    $changed = true;
                } elseif (isset($translated[$prefix.$name])) {
                    $map = Value::map($value);
                    $map[(string) $to->code] = $translated[$prefix.$name];
                    $values[$name] = $map;
                    $changed = true;
                }
            }

            foreach ($this->translatableProperties($field->fieldType()) as $property) {
                if (! isset($values[$property]) && isset($translated[$prefix.$property])) {
                    $values[$property] = [(string) $to->code => $translated[$prefix.$property]];
                    $changed = true;
                }
            }

            $items[$index] = ['values' => $values] + $item;
        }

        if ($changed) {
            $page->setLanguageValue($to, $field->name, $items);
        }
    }

    /** A text to translate: the value has one in $from, and none in $to unless overwriting. */
    private function source(mixed $value, Language $from, Language $to, bool $overwrite): ?string
    {
        $map = Value::map($value);
        $source = $map[(string) $from->code] ?? null;

        return is_string($source) && trim($source) !== '' && (! isset($map[(string) $to->code]) || $overwrite) ? $source : null;
    }

    /** @return list<string> the item properties a type keeps per language, such as a picture's description */
    private function translatableProperties(mixed $type): array
    {
        if (! $type instanceof HasItemProperties) {
            return [];
        }

        return array_keys(array_filter($type->itemProperties(), fn (ItemProperty $property): bool => $property->isTranslatable));
    }

    /**
     * @param  array<string, string>  $translated
     * @return array<string, string> the translations under a prefix, by what follows it
     */
    private function parts(array $translated, string $prefix): array
    {
        $parts = [];

        foreach ($translated as $key => $text) {
            if (str_starts_with($key, $prefix)) {
                $parts[substr($key, strlen($prefix))] = $text;
            }
        }

        return $parts;
    }

    /** @param  array<array-key, mixed>  $item */
    private function subfieldType(Field $field, array $item, string $name): mixed
    {
        $type = $field->fieldType();

        foreach ($type instanceof ItemFieldType ? $type->subfields($field->config(), Value::nullableString($item['type'] ?? null)) : [] as $subfield) {
            if ($subfield->name() === $name) {
                return $subfield->field->fieldType();
            }
        }

        return null;
    }
}
