<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * Schema text (labels, descriptions, notes) in forms as one input per language (ADR-0043). Text
 * entered only in the default language is stored as a plain string, the same in every language.
 *
 * @internal
 */
final class LocalizedForm
{
    /**
     * Languages offered as tabs: code and tab label.
     *
     * @return list<array{code: string, label: string}>
     */
    public static function languages(): array
    {
        return array_values(lynx()->languages->all()->map(fn (Language $language): array => [
            'code' => (string) $language->code,
            'label' => $language->tabLabel(),
        ])->all());
    }

    /**
     * @return array<string, string> language code => text
     */
    public static function toForm(mixed $raw): array
    {
        $text = LocalizedText::from($raw);
        $form = array_fill_keys(array_column(self::languages(), 'code'), '');

        if (! $text->isLocalized()) {
            $form[(string) lynx()->languages->default()->code] = $text->in();

            return $form;
        }

        foreach (Value::map($text->toStorage()) as $code => $value) {
            if (array_key_exists($code, $form)) {
                $form[$code] = Value::string($value);
            }
        }

        return $form;
    }

    /**
     * @param  array<array-key, mixed>  $form
     * @return string|array<string, string>|null
     */
    public static function fromForm(array $form): string|array|null
    {
        $entered = [];

        foreach ($form as $code => $value) {
            $value = trim(Value::string($value));

            if ($value !== '') {
                $entered[(string) $code] = $value;
            }
        }

        $default = (string) lynx()->languages->default()->code;

        return match (true) {
            $entered === [] => null,
            count($entered) === 1 && isset($entered[$default]) => $entered[$default],
            default => $entered,
        };
    }
}
