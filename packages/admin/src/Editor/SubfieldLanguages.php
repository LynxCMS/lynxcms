<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Support\Value;

/**
 * The language tabs' states of a repeater or matrix subfield, as FieldViews gives them for a
 * page's fields: filled, falling back through the language chain, or empty. Read from the
 * item's form values, so the tabs match the editor's other fields.
 *
 * @internal
 */
final class SubfieldLanguages
{
    /**
     * @param  array<string, mixed>  $values  language code => form value
     * @return array<string, LanguageValue> language code => state
     */
    public static function states(string $type, array $values): array
    {
        $fieldType = lynx()->fieldTypes()->get($type);
        $isFilled = fn (string $code): bool => ! $fieldType->isEmpty($values[$code] ?? null);
        $states = [];

        foreach (array_keys($values) as $code) {
            $states[$code] = $isFilled($code) ? new LanguageValue($values[$code], true) : self::fallback($code, $values, $isFilled);
        }

        return $states;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  callable(string): bool  $isFilled
     */
    private static function fallback(string $code, array $values, callable $isFilled): LanguageValue
    {
        foreach (lynx()->languages->chain($code) as $language) {
            $fallback = Value::string($language->code);

            if ($fallback !== $code && $isFilled($fallback)) {
                return new LanguageValue($values[$fallback], false, Value::string($language->name));
            }
        }

        return new LanguageValue(null, false);
    }
}
