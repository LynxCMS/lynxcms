<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\UseSettings;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Normalises field lists given to template actions ("title" or {"name": "title", "required": true})
 * and writes them to lynx_template_fields.
 *
 * @internal
 */
final readonly class TemplateFieldsWriter
{
    private const array PIVOT_KEYS = ['required', 'label', 'show_if', 'required_if', 'description', 'notes', 'allowed_types', 'tab', 'column', 'section', 'searchable', 'editor', 'width', 'placeholder', 'icon', ...UseSettings::KEYS];

    /** Pivot settings editors read, as LocalizedText (ADR-0043), with their maximum length. */
    private const array LOCALIZED_KEYS = ['label' => 255, 'description' => 2000, 'notes' => 500, 'placeholder' => 255];

    public function __construct(private FieldRegistry $fields) {}

    /**
     * @param  array<mixed>  $specs
     * @return array<string, array<string, mixed>> field name => pivot settings, in order
     */
    public function normalize(array $specs): array
    {
        $result = [];

        foreach ($specs as $spec) {
            $spec = is_string($spec) ? ['name' => $spec] : Value::map($spec);
            $name = Value::string($spec['name'] ?? '');

            $field = $this->fields->find($name);

            if ($field === null) {
                throw ValidationException::withMessages(['fields' => "Field [{$name}] does not exist. Create it first with lynx()->fields->add('{$name}', ...) or the fields.create action."]);
            }

            $result[$name] = array_intersect_key($spec, array_flip(self::PIVOT_KEYS));
            $this->assertLocalized($name, $result[$name]);
            $problem = UseSettings::problem($field, $result[$name]);

            if ($problem !== null) {
                throw ValidationException::withMessages(['fields' => $problem]);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function assertLocalized(string $name, array $settings): void
    {
        $rules = [];

        foreach (self::LOCALIZED_KEYS as $key => $max) {
            $rules[$key] = ['sometimes', 'nullable', ...LocalizedText::rules($max)];
        }

        $validator = Validator::make($settings, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['fields' => "Field [{$name}]: ".$validator->errors()->first()]);
        }
    }

    /**
     * Replace the template's field list.
     *
     * @param  array<string, array<string, mixed>>  $fields
     */
    public function write(Template $template, array $fields): void
    {
        $this->assertConditions($fields);

        foreach ($fields as $name => $settings) {
            EditorSettings::assert($name, $settings);
        }

        DB::table(Tables::TEMPLATE_FIELDS)->where('template_id', $template->id)->delete();
        $sort = 0;

        foreach ($fields as $name => $settings) {
            DB::table(Tables::TEMPLATE_FIELDS)->insert([
                'template_id' => $template->id,
                'field_id' => $this->fields->get($name)->id,
                'sort' => $sort++,
                'settings' => $settings === [] ? null : json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * Conditions must parse and read only other fields of the same template (ADR-0030).
     *
     * @param  array<string, array<string, mixed>>  $fields
     */
    private function assertConditions(array $fields): void
    {
        foreach ($fields as $name => $settings) {
            foreach (['show_if', 'required_if'] as $key) {
                $condition = trim(Value::string($settings[$key] ?? ''));

                if ($condition !== '') {
                    $this->assertCondition($name, $key, $condition, array_keys($fields));
                }
            }
        }
    }

    /**
     * @param  list<string>  $templateFields
     */
    private function assertCondition(string $name, string $key, string $condition, array $templateFields): void
    {
        try {
            $read = FieldCondition::parse($condition)->fields();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['fields' => "The {$key} of [{$name}]: {$exception->getMessage()}"]);
        }

        if (in_array($name, $read, true)) {
            throw ValidationException::withMessages(['fields' => "The {$key} of [{$name}] cannot depend on itself."]);
        }

        $missing = array_diff($read, $templateFields);

        if ($missing !== []) {
            throw ValidationException::withMessages(['fields' => "The {$key} of [{$name}] reads [".implode(', ', $missing).'], which is not on this template.']);
        }
    }
}
