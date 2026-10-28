<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Fields\DefaultValues;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Value;

/**
 * Cleans and checks item field input (ADR-0044): every item gets a key, its subfield values go
 * through their own field types, translations through every language they name. Values of fields
 * the item does not show (another matrix type) are kept untouched (rule 6). Errors are keyed by
 * item index, subfield and language ("2.role.pt").
 *
 * @phpstan-import-type ItemValue from ItemRows
 *
 * @internal
 */
final class ItemNormalizer
{
    /** @var array<string, string> */
    private array $errors = [];

    public function __construct(private readonly LanguageRegistry $languages) {}

    /**
     * @param  array<string, mixed>  $settings
     * @param  Closure(?string): list<Subfield>  $subfields  the fields an item of a type shows
     * @param  Closure(mixed, int): ?string  $type  the item's type, null for repeaters; reports errors itself
     * @return list<ItemValue>
     */
    public function normalize(mixed $value, array $settings, Closure $subfields, Closure $type): array
    {
        $this->errors = [];
        $items = [];

        foreach ($value === null ? [] : (is_array($value) && array_is_list($value) ? $value : $this->fail('', 'The items must be a list.')) as $index => $raw) {
            $raw = is_array($raw) ? $raw : [];
            $itemType = $type($raw['type'] ?? null, $index);
            $fields = $subfields($itemType);
            $values = Value::map($raw['values'] ?? []);
            // An item without a key is new, so the fields it leaves out start with their defaults (ADR-0088).
            $isNew = ($raw['key'] ?? null) === null || $raw['key'] === '';
            $items[] = [
                'key' => $this->key($raw['key'] ?? null, $index),
                'type' => $itemType,
                'enabled' => (bool) ($raw['enabled'] ?? true),
                'depth' => max(0, min(Value::int($settings['max_depth'] ?? 0), Value::int($raw['depth'] ?? 0))),
                'values' => $this->values($isNew ? DefaultValues::fillNewItem($fields, $values) : $values, $fields, $index),
            ];
        }

        $this->count(count($items), $settings);

        if ($this->errors !== []) {
            throw ValidationException::withMessages($this->errors);
        }

        return $items;
    }

    public function error(string $key, string $message): void
    {
        $this->errors[$key] ??= $message;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<Subfield>  $subfields
     * @return array<string, mixed>
     */
    private function values(array $values, array $subfields, int $index): array
    {
        $result = [];

        foreach ($values as $name => $value) {
            $result[(string) $name] = $value;
        }

        foreach ($subfields as $subfield) {
            $name = $subfield->name();
            $normalized = array_key_exists($name, $result)
                ? ($subfield->isTranslatable() ? $this->translated($subfield, $result[$name], "{$index}.{$name}") : $this->one($subfield, $result[$name], "{$index}.{$name}"))
                : null;
            unset($result[$name]);

            if (! in_array($normalized, [null, [], ''], true)) {
                $result[$name] = $normalized;
            } elseif ($subfield->isRequired()) {
                $this->error("{$index}.{$name}", "{$subfield->label()} is required.");
            }

            if ($subfield->isTranslatable() && $subfield->isRequired() && is_array($normalized) && ! isset($normalized[$this->languages->default()->code])) {
                $this->error("{$index}.{$name}", "{$subfield->label()} is required in the default language.");
            }
        }

        return $result;
    }

    /**
     * A translatable subfield: a map of language codes (a plain value counts as the default language).
     *
     * @return array<string, mixed>
     */
    private function translated(Subfield $subfield, mixed $value, string $key): array
    {
        $map = is_array($value) && ! array_is_list($value) ? $value : [(string) $this->languages->default()->code => $value];
        $result = [];

        foreach ($map as $code => $languageValue) {
            if (! $this->languages->find((string) $code) instanceof Language) {
                $this->error("{$key}.{$code}", "There is no language [{$code}].");

                continue;
            }

            $normalized = $this->one($subfield, $languageValue, "{$key}.{$code}");

            if (! in_array($normalized, [null, '', []], true)) {
                $result[(string) $code] = $normalized;
            }
        }

        return $result;
    }

    private function one(Subfield $subfield, mixed $value, string $key): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $subfield->type();
        $settings = $subfield->config();

        try {
            $normalized = $type->normalize($value, $settings);
        } catch (ValidationException $exception) {
            $this->error($key, Value::string(collect($exception->errors())->flatten()->first()));

            return null;
        }

        $validator = Validator::make(['value' => $normalized], ['value' => $type->rules($settings)]);

        if ($validator->fails()) {
            $this->error($key, $validator->errors()->first('value'));

            return null;
        }

        return $type->isEmpty($normalized) ? null : $normalized;
    }

    private function key(mixed $key, int $index): string
    {
        if ($key === null || $key === '') {
            return (string) Str::ulid();
        }

        if (! is_string($key) || preg_match('/^[0-9A-Z]{26}$/', $key) !== 1) {
            $this->error("{$index}.key", 'An item key is a ULID: leave it out for new items.');

            return (string) Str::ulid();
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function count(int $count, array $settings): void
    {
        $max = Value::int($settings['max'] ?? 0);
        $min = Value::int($settings['min'] ?? 0);

        if ($max > 0 && $count > $max) {
            $this->error('', "There may be at most {$max} items.");
        } elseif ($count > 0 && $count < $min) {
            $this->error('', "There must be at least {$min} items.");
        }
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
