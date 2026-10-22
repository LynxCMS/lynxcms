<?php

declare(strict_types=1);

namespace LynxCms\Core\Content;

use Closure;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Support\Value;

/**
 * Page references in a content package (ADR-0042): the package names pages by key, so import
 * writes reference fields only once every page exists, mapping the keys back to this site's ids
 * through each field type.
 *
 * @internal
 */
final readonly class PackageReferences
{
    public function __construct(private FieldRegistry $fields) {}

    /**
     * The line's shared reference values, still holding page keys.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public function of(array $line): array
    {
        $shared = Value::map(Value::map($line['values'] ?? [])['*'] ?? []);

        return array_intersect_key($shared, array_flip($this->fieldNames()));
    }

    /**
     * The values without reference fields.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function without(array $values): array
    {
        return array_diff_key($values, array_flip($this->fieldNames()));
    }

    /**
     * Page keys back to ids; $idOfKey returns null for a key that was not imported.
     *
     * @param  array<string, mixed>  $values
     * @param  Closure(int|string): ?int  $idOfKey
     * @return array<string, mixed>
     */
    public function resolve(array $values, Closure $idOfKey): array
    {
        $resolved = [];

        foreach ($values as $name => $value) {
            $field = $this->fields->find($name);
            $type = $field?->fieldType();
            $resolved[$name] = $type instanceof ReferencesPages && $field !== null
                ? $type->mapPageIds($value, $idOfKey, $field->config())
                : $value;
        }

        return $resolved;
    }

    /** @return list<string> */
    private function fieldNames(): array
    {
        return Value::strings($this->fields->all()->filter(fn ($field): bool => $field->fieldType() instanceof ReferencesPages)->keys()->all());
    }
}
