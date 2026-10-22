<?php

declare(strict_types=1);

namespace LynxCms\Core\Api;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Fields\DeleteField;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Models\Field;

/**
 * $lynx->fields
 *
 * @api
 */
final readonly class FieldsApi
{
    public function __construct(
        private ActionRunner $runner,
        private FieldRegistry $registry,
        private FieldTypeRegistry $types,
    ) {}

    /**
     * @param  string|array<string, string>|null  $label  text, or language code => text (ADR-0043)
     * @param  array<string, mixed>  $settings
     */
    public function add(string $name, string $type, bool $translatable = false, string|array|null $label = null, array $settings = [], bool $dryRun = false): Field|ActionResult
    {
        $input = ['name' => $name, 'type' => $type, 'translatable' => $translatable, 'settings' => $settings] + ($label === null ? [] : ['label' => $label]);

        return $this->runner->run(CreateField::class, $input, $dryRun)->valueOrDryRun(Field::class);
    }

    /**
     * @param  array<string, mixed>  $changes  label, settings
     */
    public function update(Field|string $field, array $changes, bool $dryRun = false): Field|ActionResult
    {
        return $this->runner->run(UpdateField::class, ['field' => $this->name($field)] + $changes, $dryRun)->valueOrDryRun(Field::class);
    }

    public function delete(Field|string $field, bool $dryRun = false): Field|ActionResult
    {
        return $this->runner->run(DeleteField::class, ['field' => $this->name($field)], $dryRun)->valueOrDryRun(Field::class);
    }

    public function get(string $name): Field
    {
        return $this->registry->get($name);
    }

    public function find(string $name): ?Field
    {
        return $this->registry->find($name);
    }

    /**
     * @return Collection<string, Field>
     */
    public function all(): Collection
    {
        return $this->registry->all();
    }

    /**
     * Field types with their description and settings schema, for editors and AI agents.
     *
     * @return array<string, array<string, mixed>>
     */
    public function types(): array
    {
        $result = [];

        foreach ($this->types->all() as $name => $class) {
            $type = $this->types->get($name);
            $settings = array_map(fn (Type $schema): array => $schema->toArray(), $type->settingsSchema(new JsonSchemaTypeFactory));

            $result[$name] = [
                'description' => $class::description(),
                'translatable' => $type->supportsTranslation(),
                'multiple' => $type->multiple(),
                'settings' => $settings,
            ];
        }

        return $result;
    }

    private function name(Field|string $field): string
    {
        return $field instanceof Field ? $field->name : $field;
    }
}
