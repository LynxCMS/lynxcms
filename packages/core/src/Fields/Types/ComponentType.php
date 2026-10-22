<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Components\ComponentRegistry;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * An app component (usually Livewire) chosen by editors, with its settings (ADR-0021).
 * Stores {"component": alias, "settings": {...}}. Shared across languages.
 */
final class ComponentType extends BaseFieldType implements DescribesPhpType
{
    public function __construct(private readonly ComponentRegistry $components) {}

    public static function name(): string
    {
        return 'component';
    }

    public static function description(): string
    {
        return 'An app component registered with Lynx::components(), e.g. an enrolment form, plus its settings. Value: {"component": "<alias>", "settings": {...}}. Setting "allowed" limits the choice.';
    }

    public function indexable(): bool
    {
        return false;
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->json('data')->nullable();
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return ['allowed' => $schema->array()->items($schema->string())->description('Aliases of components editors may choose.')];
    }

    public function settingsRules(): array
    {
        return ['allowed' => ['sometimes', 'array'], 'allowed.*' => ['string']];
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'array'];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $value = is_string($value) ? ['component' => $value] : Value::map($value);
        $alias = Value::string($value['component'] ?? '');
        $component = $this->components->find($alias);

        if ($component === null || (isset($settings['allowed']) && ! in_array($alias, Value::strings($settings['allowed']), true))) {
            throw ValidationException::withMessages(['component' => "Component [{$alias}] is not registered or not allowed here."]);
        }

        return ['component' => $alias, 'settings' => $component->validateSettings(Value::map($value['settings'] ?? []))];
    }

    public function sleep(mixed $value, array $settings): array
    {
        return [['data' => $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR)]];
    }

    public function wake(array $rows, array $settings): mixed
    {
        return $rows === [] || $rows[0]->data === null ? null : json_decode(Value::string($rows[0]->data), true, 512, JSON_THROW_ON_ERROR);
    }

    public function comparison(): Comparison
    {
        return Comparison::None;
    }

    public function phpType(Field $field): string
    {
        return 'array<string, mixed>|null';
    }
}
