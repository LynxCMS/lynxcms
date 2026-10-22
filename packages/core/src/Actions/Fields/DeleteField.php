<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Fields;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldSchemaManager;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Deletes a field, removes it from every template and drops its table with all values. Refuses
 * while a repeater or matrix shows it in its items, so no item field points at a missing field.
 */
final class DeleteField extends Action
{
    public function __construct(
        private readonly FieldRegistry $fields,
        private readonly TemplateRegistry $templates,
        private readonly FieldSchemaManager $schema,
    ) {}

    public static function name(): string
    {
        return 'fields.delete';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Delete a field everywhere: removes it from all templates and drops its table with every stored value.';
    }

    public static function destructive(): bool
    {
        return true;
    }

    public static function rules(): array
    {
        return ['field' => ['required', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['field' => $schema->string()->description('Name of the field to delete.')->required()];
    }

    public function handle(Input $input, ActionContext $context): Field
    {
        $field = $this->fields->get((string) $input->string('field'));
        $itemFields = $this->fields->itemFieldsUsing($field->name);

        if ($itemFields !== []) {
            throw ValidationException::withMessages(['field' => "Field [{$field->name}] is a subfield of ".implode(', ', $itemFields).'. Remove it there first.']);
        }

        $values = DB::table($field->table())->count();
        $templates = $this->templateNames($field->id);

        DB::table(Tables::TEMPLATE_FIELDS)->where('field_id', $field->id)->delete();
        DB::table(Tables::FIELD_VALUE_META)->where('field_id', $field->id)->delete();
        $this->schema->drop($field);
        Field::query()->whereKey($field->id)->delete();
        $this->fields->flush();
        $this->templates->flush();

        $removes = $templates === [] ? '' : '; removes it from templates '.implode(', ', $templates);
        $context->changes->schema('field', "Delete field {$field->name} and {$values} stored values{$removes}", [
            'name' => $field->name,
            'values' => $values,
            'templates' => $templates,
        ], destructive: true);

        return $field;
    }

    /**
     * @return list<string> names of the templates that use the field, sorted
     */
    private function templateNames(int $fieldId): array
    {
        return Value::strings(DB::table(Tables::TEMPLATE_FIELDS)
            ->join(Tables::TEMPLATES, Tables::TEMPLATES.'.id', '=', Tables::TEMPLATE_FIELDS.'.template_id')
            ->where(Tables::TEMPLATE_FIELDS.'.field_id', $fieldId)
            ->orderBy(Tables::TEMPLATES.'.name')
            ->pluck(Tables::TEMPLATES.'.name')
            ->all());
    }
}
