<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Fields\SettingFormats;
use LynxCms\Core\Support\Value;

/**
 * A form generated from a JSON schema (a component's settings, a field type's settings): one input
 * per property, and the submitted values cast back to the schema's types. Lists are typed as
 * comma separated text, a set of checkboxes when their items are an enum, or a template chooser when
 * their items have the template format (SettingFormats). Validation stays with
 * whoever owns the schema.
 *
 * @internal
 */
final class SchemaForm
{
    /**
     * @param  array<array-key, mixed>  $properties  property name => JSON schema as an array
     * @return array<string, array{type: string, description: string, enum: list<string>, format: string}>
     */
    public function definitions(array $properties): array
    {
        $definitions = [];

        foreach ($properties as $name => $schema) {
            $schema = Value::map($schema);
            $definitions[(string) $name] = [
                'type' => Value::string($schema['type'] ?? 'string'),
                'description' => Value::string($schema['description'] ?? ''),
                // A list's choices are its items' enum.
                'enum' => Value::strings($schema['enum'] ?? Value::map($schema['items'] ?? [])['enum'] ?? []),
                // What the strings hold, such as template names (SettingFormats), the list's from its items.
                'format' => Value::string($schema['format'] ?? Value::map($schema['items'] ?? [])['format'] ?? ''),
            ];
        }

        return $definitions;
    }

    /**
     * Form values to typed settings. Empty values are dropped, or kept as null when $emptyAsNull,
     * so an update can remove a setting.
     *
     * @param  array<string, array{type: string, description: string, enum: list<string>, format: string}>  $definitions
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    public function cast(array $definitions, array $values, bool $emptyAsNull = false): array
    {
        $cast = [];

        foreach ($definitions as $name => $definition) {
            $value = $values[$name] ?? null;

            if ($value === null || $value === '' || $value === []) {
                if ($emptyAsNull) {
                    $cast[$name] = null;
                }

                continue;
            }

            $cast[$name] = match ($definition['type']) {
                'integer' => is_numeric($value) ? (int) $value : $value,
                'number' => is_numeric($value) ? (float) $value : $value,
                'boolean' => (bool) $value,
                'array' => is_array($value) ? $value : array_values(array_filter(array_map(trim(...), explode(',', Value::string($value))), fn (string $item): bool => $item !== '')),
                default => $value,
            };
        }

        return $cast;
    }

    /**
     * Stored settings as form values: lists joined with commas (lists of choices or templates stay lists), numbers as text.
     *
     * @param  array<string, array{type: string, description: string, enum: list<string>, format: string}>  $definitions
     * @param  array<array-key, mixed>  $settings
     * @return array<string, mixed>
     */
    public function toForm(array $definitions, array $settings): array
    {
        $form = [];

        foreach ($definitions as $name => $definition) {
            $value = $settings[$name] ?? null;
            $form[$name] = match (true) {
                $definition['type'] === 'boolean' => $value === null ? null : (bool) $value,
                $definition['type'] === 'array' && ($definition['enum'] !== [] || $definition['format'] === SettingFormats::TEMPLATE) => Value::strings($value ?? []),
                is_array($value) => implode(', ', Value::strings($value)),
                $value === null => '',
                default => Value::string($value),
            };
        }

        return $form;
    }
}
