<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Illuminate\Support\Str;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Support\Value;

/**
 * Writes a field in the short form of a draft (ADR-0116), the reverse of DraftShorthand, or says it
 * can't: a value with quotes, nested settings, labels per language, or choice options whose
 * values aren't their labels slugged. DraftWriter still checks that the result reads back as the
 * same field.
 *
 * @phpstan-import-type FieldItem from SchemaSync
 *
 * @internal
 */
final readonly class DraftValues
{
    private const string PLAIN = '/^[^\s",]+$/';

    public function __construct(private DraftShorthand $shorthand) {}

    /**
     * @param  FieldItem  $item
     */
    public function shortForm(array $item): ?string
    {
        $settings = $item['settings'];
        $head = $item['type'];
        $argument = DraftShorthand::ARGUMENTS[$item['type']] ?? null;

        if ($argument !== null && array_key_exists($argument, $settings)) {
            $value = $argument === 'options' ? $this->options($settings['options']) : $this->value($settings[$argument], inList: false);
            unset($settings[$argument]);

            if ($value === null) {
                return null;
            }

            $head .= ':'.$value;
        }

        return $this->tokens($item, $settings, $head);
    }

    /**
     * @param  FieldItem  $item
     * @param  array<string, mixed>  $settings
     */
    private function tokens(array $item, array $settings, string $head): ?string
    {
        $tokens = [$head];

        if ($item['translatable'] !== $this->shorthand->translatesByDefault($item['type'])) {
            $tokens[] = $item['translatable'] ? 'translatable' : 'shared';
        }

        if ($item['label'] !== DraftShorthand::label($item['name'])) {
            $label = is_string($item['label']) ? $this->quoted($item['label']) : null;

            if ($label === null) {
                return null;
            }

            $tokens[] = DraftShorthand::LABEL_TOKEN.$label;
        }

        foreach ($settings as $key => $value) {
            $token = $value === true ? $key : $this->value($value, inList: false);

            if ($token === null) {
                return null;
            }

            $tokens[] = $value === true ? $key : "{$key}:{$token}";
        }

        return implode(' ', $tokens);
    }

    private function value(mixed $value, bool $inList): ?string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) && ! $inList => $this->quoted($value),
            is_string($value) => preg_match(self::PLAIN, $value) === 1 ? $value : null,
            is_array($value) && array_is_list($value) && $value !== [] => $this->list($value),
            default => null,
        };
    }

    /** @param  list<mixed>  $values */
    private function list(array $values): ?string
    {
        $items = array_map(fn (mixed $item): ?string => $this->value($item, inList: true), $values);

        return in_array(null, $items, true) ? null : implode(',', $items);
    }

    /** Options written as their labels, when each value is its label slugged. */
    private function options(mixed $options): ?string
    {
        $labels = [];

        foreach (Value::list($options) as $option) {
            $option = Value::map($option);
            $label = $option['label'] ?? null;

            if (! is_string($label) || ($option['value'] ?? null) !== Str::slug($label) || str_contains($label, '"') || str_contains($label, ',')) {
                return null;
            }

            $labels[] = preg_match(self::PLAIN, $label) === 1 ? $label : '"'.$label.'"';
        }

        return $labels === [] ? null : implode(',', $labels);
    }

    private function quoted(string $text): ?string
    {
        if (preg_match(self::PLAIN, $text) === 1) {
            return $text;
        }

        return str_contains($text, '"') || $text === '' ? null : '"'.$text.'"';
    }
}
