<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Rules\FormName;
use LynxCms\Forms\Transfer\FormExporter;
use LynxCms\Forms\Transfer\FormTexts;

/**
 * Creates a form from another site's export (ADR-0146): forms.create, then its fields and messages
 * with pages.save, unpublished and without recipients, so the owner adds them before publishing.
 * Texts in the source's default language become this site's default language texts, and texts
 * in languages this site lacks are dropped and listed. The builder's own checks run on the save.
 *
 * @api
 */
final class ImportForm extends Action
{
    public static function name(): string
    {
        return 'forms.import';
    }

    public static function description(): string
    {
        return 'Create a form from a form export (the JSON of lynx:forms:export or the admin\'s Export): its title, messages and fields, unpublished and without recipients. name gives it another key, for a name the site has. Returns the form\'s name and the languages whose texts were dropped because this site lacks them.';
    }

    public static function permission(): string
    {
        return 'forms.manage';
    }

    public static function rules(): array
    {
        $types = array_column(FormSchema::blockTypes(), 'slug');

        return [
            'form' => ['required', 'array'],
            'form.lynx_form' => ['required', Rule::in([FormExporter::FORMAT])],
            'form.name' => ['required', 'string', new FormName],
            'form.default_language' => ['required', 'string'],
            'form.title' => ['nullable', 'array'],
            'form.messages' => ['sometimes', 'array'],
            'form.fields' => ['present', 'array', 'list'],
            'form.fields.*.type' => ['required', Rule::in($types)],
            'form.fields.*.depth' => ['sometimes', 'integer', 'min:0', 'max:1'],
            'form.fields.*.enabled' => ['sometimes', 'boolean'],
            'form.fields.*.values' => ['present', 'array'],
            'name' => ['sometimes', 'string', new FormName],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'form' => $schema->object()->description('The export: {lynx_form: 1, name, default_language, title, messages: {success, error, auto_reply}, fields: [{type, depth, enabled, values}]}.')->required(),
            'name' => $schema->string()->description('The new form\'s key, when it differs from the export\'s: lowercase letters, digits and dashes.'),
        ];
    }

    /**
     * @return array{name: string, page: int, dropped_languages: list<string>}
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $file = Value::map($input->get('form'));
        $name = $input->string('name') ?? Value::string($file['name']);
        $codes = Value::strings(lynx()->languages->all()->map(fn ($language): string => (string) $language->code)->values()->all());
        $texts = new FormTexts(Value::string($file['default_language']), (string) lynx()->languages->default()->code, $codes);
        $blocks = Value::list($file['fields']);
        $fields = array_map(fn (mixed $block, int $index): array => $this->block(Value::map($block), $index, $texts), $blocks, array_keys($blocks));

        $page = $context->run(CreateForm::class, ['name' => $name, 'label' => $texts->map($file['title'] ?? null) ?? $name]);

        if (! $page instanceof Page) {
            throw ValidationException::withMessages(['form' => Value::string(__('lynx-forms::validation.form.failed'))]);
        }

        $values = [FormSchema::FIELDS => $fields];

        foreach (FormExporter::MESSAGES as $key => $field) {
            $values[$field] = $texts->map(Value::map($file['messages'] ?? [])[$key] ?? null);
        }

        $context->run(SavePage::class, ['id' => $page->id, 'values' => array_filter($values, fn (mixed $value): bool => $value !== null)]);

        return ['name' => $name, 'page' => (int) $page->id, 'dropped_languages' => $texts->dropped()];
    }

    /**
     * A block with its texts keyed by this site's languages, refused when it has a value its type
     * doesn't show.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function block(array $block, int $index, FormTexts $texts): array
    {
        $type = Value::string($block['type']);
        $allowed = array_column(Value::list(collect(FormSchema::blockTypes())->firstWhere('slug', $type)['fields'] ?? []), 'name');
        $values = Value::map($block['values']);

        foreach (array_keys($values) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages(["form.fields.{$index}.values" => Value::string(__('lynx-forms::validation.import.value', ['value' => $key, 'type' => $type]))]);
            }
        }

        foreach ([FormSchema::LABEL, FormSchema::HELP] as $text) {
            if (isset($values[$text])) {
                $values[$text] = $texts->map($values[$text]);
            }
        }

        if (isset($values[FormSchema::OPTIONS])) {
            $values[FormSchema::OPTIONS] = array_map(fn (mixed $option): array => [...Value::map($option), 'label' => $texts->map(Value::map($option)['label'] ?? null)], Value::list($values[FormSchema::OPTIONS]));
        }

        return ['type' => $type, 'depth' => Value::int($block['depth'] ?? 0), 'enabled' => Value::bool($block['enabled'] ?? true), 'values' => $values];
    }
}
