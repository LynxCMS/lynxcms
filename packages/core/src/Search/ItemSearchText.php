<?php

declare(strict_types=1);

namespace LynxCms\Core\Search;

use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\ProvidesSearchText;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Value;

/**
 * The search text of a repeater or matrix value (ADR-0054): the text of every enabled item's
 * fields, each translation read with the language's fallback chain.
 *
 * @internal
 */
final readonly class ItemSearchText
{
    public function __construct(private LanguageRegistry $languages) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public function of(ItemFieldType $type, mixed $value, array $settings, Language $language): string
    {
        $codes = array_map(fn (Language $one): string => $one->code, $this->languages->chain($language));
        $parts = [];

        foreach (Value::list($value) as $item) {
            $item = Value::array($item);

            if (($item['enabled'] ?? true) === false) {
                continue;
            }

            $values = Value::map($item['values'] ?? []);
            $itemType = isset($item['type']) ? Value::string($item['type']) : null;

            foreach ($type->subfields($settings, $itemType) as $subfield) {
                $fieldType = $subfield->type();

                if ($fieldType instanceof ProvidesSearchText) {
                    $raw = $values[$subfield->name()] ?? null;
                    $one = $subfield->isTranslatable() ? $this->pick(Value::map($raw), $codes) : $raw;
                    $parts[] = $fieldType->searchText($one, $subfield->field->config(), $language);
                }
            }
        }

        return implode(' ', $parts);
    }

    /**
     * The first translation along the fallback chain.
     *
     * @param  array<string, mixed>  $translations
     * @param  list<string>  $codes
     */
    private function pick(array $translations, array $codes): mixed
    {
        foreach ($codes as $code) {
            if (($translations[$code] ?? null) !== null) {
                return $translations[$code];
            }
        }

        return null;
    }
}
