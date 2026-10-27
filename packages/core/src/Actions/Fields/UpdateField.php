<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Fields;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldSettings;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * Changes a field's label or settings. Renaming and changing scope come later (they move data).
 */
final class UpdateField extends Action
{
    public function __construct(private readonly FieldRegistry $fields) {}

    public static function name(): string
    {
        return 'fields.update';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Change a field\'s label or settings. Settings are merged with the existing ones.';
    }

    public static function rules(): array
    {
        return [
            'field' => ['required', 'string'],
            'label' => ['sometimes', ...LocalizedText::rules()],
            'settings' => ['sometimes', 'array'],
            'settings.fallback' => ['sometimes', 'nullable', Rule::in(['inherit', 'none'])],
            'settings.autoload' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'field' => $schema->string()->description('Name of the field.')->required(),
            'label' => $schema->string(),
            'settings' => $schema->object()->description('Settings to merge into the existing ones; a setting given as null is removed.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Field
    {
        $field = Field::query()->findOrFail($this->fields->get((string) $input->string('field'))->id);

        if ($input->has('label')) {
            $field->label = LocalizedText::from($input->get('label'))->toColumn();
        }

        if ($input->has('settings')) {
            $settings = $input->array('settings');
            // Normalized settings (NormalizesSettings) replace what was given; nulls still remove.
            $settings = array_merge($settings, FieldSettings::validate($field->fieldType(), $settings));
            // A setting given as null is removed, so it falls back to the type's default again.
            $merged = array_filter(array_merge($field->config(), Value::map($settings)), fn (mixed $value): bool => $value !== null);
            FieldSettings::check($field->fieldType(), $merged);
            $field->settings = $merged;
        }

        $field->save();
        $this->fields->flush();
        $context->changes->updated('field', $field->id, "Update field {$field->name}", $field->toSummary());

        return $field;
    }
}
