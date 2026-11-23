<?php

declare(strict_types=1);

namespace LynxCms\Core\Content;

use Closure;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\PageLinks;
use LynxCms\Core\Fields\ReferenceFields;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Fields\Types\TextareaType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Page references in a content package (ADR-0042): the package names pages by key, so import
 * writes reference values only once every page exists, mapping the keys back to this site's ids
 * through each field type. Rich text whose links name a page (ADR-0145) is written first without
 * those names, so a required text field never blocks a page, then again with them.
 *
 * @internal
 */
final readonly class PackageReferences
{
    public function __construct(private FieldRegistry $fields) {}

    /**
     * The line's reference values, still holding page keys, as pages.save takes them: a shared
     * field's value, or a translatable field's values by language code.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public function of(array $line): array
    {
        $shared = [];
        $byLanguage = [];

        foreach (Value::map($line['values'] ?? []) as $code => $values) {
            foreach (Value::map($values) as $name => $value) {
                $field = $this->referenceField((string) $name);

                if ($field === null || $field->isTranslatable() === ($code === '*') || ! $this->namesPages($field, $value)) {
                    continue;
                }

                if ($field->isTranslatable()) {
                    $byLanguage[$name][(string) $code] = $value;
                } else {
                    $shared[$name] = $value;
                }
            }
        }

        return $shared + $byLanguage;
    }

    /**
     * The values for the first write: without reference fields, and rich text without page names.
     *
     * @param  array<string, mixed>  $values  as pages.save takes them
     * @return array<string, mixed>
     */
    public function without(array $values): array
    {
        foreach ($values as $name => $value) {
            $field = $this->referenceField((string) $name);

            if ($field instanceof Field && $field->fieldType() instanceof TextareaType) {
                $values[$name] = $this->mapped($field, $value, fn (int|string $key): null => null);
            } elseif ($field instanceof Field) {
                unset($values[$name]);
            }
        }

        return $values;
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
            $field = $this->referenceField((string) $name);
            $resolved[$name] = $field instanceof Field ? $this->mapped($field, $value, $idOfKey) : $value;
        }

        return $resolved;
    }

    /**
     * @param  Closure(int|string): (int|string|null)  $map
     */
    private function mapped(Field $field, mixed $value, Closure $map): mixed
    {
        $type = $field->fieldType();

        if (! $type instanceof ReferencesPages) {
            return $value;
        }

        if ($field->isTranslatable() && is_array($value)) {
            return array_map(fn (mixed $one): mixed => $type->mapPageIds($one, $map, $field->config()), $value);
        }

        return $type->mapPageIds($value, $map, $field->config());
    }

    /** Rich text counts only when one of its links names a page. */
    private function namesPages(Field $field, mixed $value): bool
    {
        return ! $field->fieldType() instanceof TextareaType || (is_string($value) && str_contains($value, PageLinks::ATTRIBUTE.'='));
    }

    private function referenceField(string $name): ?Field
    {
        $field = $this->fields->find($name);

        return $field instanceof Field && ReferenceFields::holds($field) ? $field : null;
    }
}
