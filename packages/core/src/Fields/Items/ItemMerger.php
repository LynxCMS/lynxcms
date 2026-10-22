<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use Closure;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Support\Value;

/**
 * Merges item field input for one language into the stored items (ADR-0044): items are matched by
 * key; a plain value of a translatable subfield is that language's value (null removes it, so it
 * falls back); a language map sets the languages it names; subfields the input leaves out keep
 * their stored values. Items missing from the input are removed.
 *
 * @internal
 */
final readonly class ItemMerger
{
    public function __construct(private FieldRegistry $fields) {}

    /**
     * @param  (Closure(string): bool)|null  $translated  whether a value key holds languages; by default, translatable fields
     */
    public function merge(mixed $stored, mixed $input, string $languageCode, ?Closure $translated = null): mixed
    {
        $translated ??= fn (string $name): bool => $this->fields->find($name)?->isTranslatable() === true;

        if (! is_array($input) || ! array_is_list($input)) {
            return $input;
        }

        $existing = $this->byKey($stored);
        $merged = [];

        foreach ($input as $item) {
            $merged[] = is_array($item) ? $this->mergeItem($existing[Value::string($item['key'] ?? '')] ?? [], $item, $languageCode, $translated) : $item;
        }

        return $merged;
    }

    /**
     * @return array<string, array<array-key, mixed>> stored items by key
     */
    private function byKey(mixed $stored): array
    {
        $existing = [];

        foreach (Value::array($stored) as $item) {
            $item = Value::array($item);
            $existing[Value::string($item['key'] ?? '')] = $item;
        }

        return $existing;
    }

    /**
     * The input item over its stored version: translatable values keep the other languages.
     *
     * @param  array<array-key, mixed>  $base
     * @param  array<array-key, mixed>  $item
     * @param  Closure(string): bool  $translated
     * @return array<array-key, mixed>
     */
    private function mergeItem(array $base, array $item, string $languageCode, Closure $translated): array
    {
        $values = Value::array($base['values'] ?? []);

        foreach (Value::array($item['values'] ?? []) as $name => $value) {
            $values[(string) $name] = $translated((string) $name) ? $this->languages($values[(string) $name] ?? null, $value, $languageCode) : $value;
        }

        return array_merge($base, array_diff_key($item, ['values' => true]), ['values' => $values]);
    }

    /** @return array<array-key, mixed> */
    private function languages(mixed $stored, mixed $input, string $code): array
    {
        $map = is_array($stored) && ! array_is_list($stored) ? $stored : [];

        if (is_array($input) && $input !== [] && ! array_is_list($input)) {
            foreach ($input as $languageCode => $languageValue) {
                $map[(string) $languageCode] = $languageValue;
            }
        } else {
            $map[$code] = $input;
        }

        return array_filter($map, fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
