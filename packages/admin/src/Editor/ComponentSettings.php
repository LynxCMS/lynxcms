<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use LynxCms\Core\Components\RegisteredComponent;
use LynxCms\Core\Support\Value;

/**
 * The settings form of a component field, read from the component's settings schema, and the
 * casting of submitted settings back to the schema's types. Validation stays in core
 * (RegisteredComponent::validateSettings through the component field type).
 *
 * @internal
 */
final readonly class ComponentSettings
{
    /**
     * Registered components a field offers, limited to its "allowed" setting.
     *
     * @param  array<string, mixed>  $fieldSettings
     * @return array<string, string> alias => description
     */
    public function choices(array $fieldSettings): array
    {
        $allowed = isset($fieldSettings['allowed']) ? Value::strings($fieldSettings['allowed']) : null;
        $choices = [];

        foreach (lynx()->components()->all() as $component) {
            if ($allowed === null || in_array($component->alias, $allowed, true)) {
                $choices[$component->alias] = $component->description !== '' ? $component->description : $component->alias;
            }
        }

        return $choices;
    }

    public function __construct(private SchemaForm $form = new SchemaForm) {}

    /**
     * @return array<string, array{type: string, description: string, enum: list<string>}> setting name => definition
     */
    public function fields(string $alias): array
    {
        $component = lynx()->components()->find($alias);

        if (! $component instanceof RegisteredComponent) {
            return [];
        }

        $schema = array_map(fn (mixed $type): array => $type instanceof Type ? $type->toArray() : [], $component->settingsSchema(new JsonSchemaTypeFactory));

        return $this->form->definitions($schema);
    }

    /**
     * Keep only the schema's settings, drop empty ones and cast numbers and booleans.
     *
     * @param  array<array-key, mixed>  $settings
     * @return array<string, mixed>
     */
    public function cast(string $alias, array $settings): array
    {
        return $this->form->cast($this->fields($alias), $settings);
    }
}
