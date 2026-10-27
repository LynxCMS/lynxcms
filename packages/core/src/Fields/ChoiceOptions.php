<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * A list of choices, each a stable value and a label per language, as choice fields keep in
 * their settings and form fields in their values. Rows without a label are dropped; an empty value
 * is made from the default language's label.
 *
 * @api
 */
final class ChoiceOptions
{
    public const string VALUE_PATTERN = '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/';

    /**
     * An option's value: the one given, or one made from its label in the default language.
     */
    public static function valueOf(mixed $value, mixed $label): string
    {
        $value = trim(Value::string($value));

        return $value !== '' ? $value : Str::slug(LocalizedText::from($label)->in((string) lynx()->languages->default()->code));
    }

    /**
     * @param  string  $errorPrefix  prefixed to the error keys ("{prefix}{index}.value")
     * @return list<array{value: string, label: string|array<string, string>}>
     *
     * @throws ValidationException when a value is not a slug or appears twice
     */
    public static function normalize(mixed $options, string $errorPrefix = ''): array
    {
        $rows = [];
        $errors = [];

        foreach (is_array($options) ? array_values($options) : [] as $index => $raw) {
            $raw = Value::map($raw);
            $label = self::label($raw['label'] ?? '');

            if ($label === null) {
                continue;
            }

            $value = self::valueOf($raw['value'] ?? '', $label);

            if (preg_match(self::VALUE_PATTERN, $value) !== 1) {
                $errors["{$errorPrefix}{$index}.value"] = "[{$value}] is not a valid option value: use lowercase letters, digits, dashes and underscores.";
            } elseif (in_array($value, array_column($rows, 'value'), true)) {
                $errors["{$errorPrefix}{$index}.value"] = "The option value [{$value}] appears twice.";
            }

            $rows[] = ['value' => $value, 'label' => $label];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    /**
     * The labels of stored options, by value, without checking them again (they were normalized
     * when saved).
     *
     * @return array<string, string|array<string, string>> value => label
     */
    public static function labels(mixed $options): array
    {
        $labels = [];

        foreach (Value::list($options) as $option) {
            $option = Value::map($option);
            $label = $option['label'] ?? '';
            $labels[Value::string($option['value'] ?? '')] = is_array($label) ? array_map(Value::string(...), Value::map($label)) : Value::string($label);
        }

        return $labels;
    }

    /**
     * @return string|array<string, string>|null a label, or a map of language code => label; null when empty
     */
    private static function label(mixed $label): string|array|null
    {
        if (! is_array($label)) {
            $text = trim(Value::string($label));

            return $text === '' ? null : $text;
        }

        $texts = array_filter(array_map(fn (mixed $text): string => trim(Value::string($text)), Value::map($label)), fn (string $text): bool => $text !== '');

        return $texts === [] ? null : $texts;
    }
}
