<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Fields;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\LocalizedText;

/**
 * A new field like another: its type, labels, translation and settings under a new name, made with
 * fields.create (which checks the name). It is a field of its own: no template uses it yet, and
 * values are never copied.
 */
final class DuplicateField extends Action
{
    public function __construct(private readonly FieldRegistry $fields) {}

    public static function name(): string
    {
        return 'fields.duplicate';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Create a field like an existing one (type, labels, translation, settings) under a new name. No template uses it yet; values are not copied.';
    }

    public static function rules(): array
    {
        return [
            'field' => ['required', 'string'],
            'name' => ['required', 'string'],
            'label' => ['sometimes', 'nullable', ...LocalizedText::rules()],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'field' => $schema->string()->description('Name of the field to copy.')->required(),
            'name' => $schema->string()->description('Name of the new field: lowercase letters, digits and underscores, starting with a letter.')->required(),
            'label' => $schema->string()->description('Label of the new field: text, or language code => text. Default: the original\'s labels.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Field
    {
        $original = $this->fields->find((string) $input->string('field'))
            ?? throw ValidationException::withMessages(['field' => 'Field ['.$input->string('field').'] does not exist.']);

        $copy = $context->run(CreateField::class, [
            'name' => $input->string('name'),
            'type' => $original->type,
            'label' => $input->get('label') ?? $original->labels()->toStorage(),
            'translatable' => $original->isTranslatable(),
            'settings' => $original->config(),
        ]);

        return $copy instanceof Field ? $copy : $this->fields->get((string) $input->string('name'));
    }
}
