<?php

declare(strict_types=1);

namespace LynxCms\Forms\Definition;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * Laravel validation for a form's answers under "data." (ADR-0052): only the fields the answers
 * show, each group row on its own, labels as attribute names and messages in the current
 * language. Also splits valid answers into stored values and uploaded files.
 *
 * @internal
 */
final class FormValidator
{
    private const int TEXT_MAX = 255;

    private const int TEXTAREA_MAX = 5000;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $only  field names, e.g. one step's
     */
    public function make(Form $form, array $data, ?array $only = null): Validator
    {
        return $this->makeFor($form->fields(), $data, $only);
    }

    /**
     * As make(), for fields a form component changed, such as a choice with fewer options.
     *
     * @param  list<FormField>  $fields
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $only  field names, e.g. one step's
     */
    public function makeFor(array $fields, array $data, ?array $only = null): Validator
    {
        $rules = [];
        $messages = [];
        $attributes = [];

        foreach ($this->shown($fields, $data, $only) as $field) {
            $this->describe($field, 'data.'.$field->name, $rules, $messages, $attributes);
        }

        return ValidatorFactory::make(['data' => $data], $rules, $messages, $attributes);
    }

    /**
     * The answers to keep: shown fields only, without files; group rows hold their own fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function values(Form $form, array $data): array
    {
        $values = [];

        foreach ($this->shownFields($form, $data) as $field) {
            $value = $data[$field->name] ?? null;

            if ($field->type === 'file' || $value === null || $value === '') {
                continue;
            }

            $values[$field->name] = $field->type === 'group' ? $this->rows($field, $value) : $value;
        }

        return $values;
    }

    /**
     * Uploaded files of shown file fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, list<UploadedFile>> field name => files
     */
    public function files(Form $form, array $data): array
    {
        $files = [];

        foreach ($this->shownFields($form, $data) as $field) {
            if ($field->type === 'file') {
                $files[$field->name] = array_values(array_filter((array) ($data[$field->name] ?? []), fn (mixed $file): bool => $file instanceof UploadedFile));
            }
        }

        return array_filter($files);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $only
     * @return list<FormField>
     */
    private function shownFields(Form $form, array $data, ?array $only = null): array
    {
        return $this->shown($form->fields(), $data, $only);
    }

    /**
     * @param  list<FormField>  $fields
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $only
     * @return list<FormField>
     */
    private function shown(array $fields, array $data, ?array $only = null): array
    {
        return array_values(array_filter(
            $fields,
            fn (FormField $field): bool => $field->isShown($data) && ($only === null || in_array($field->name, $only, true)),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(FormField $group, mixed $value): array
    {
        $names = array_map(fn (FormField $field): string => $field->name, $group->fields);

        return array_values(array_map(
            fn (mixed $row): array => array_intersect_key(is_array($row) ? $row : [], array_flip($names)),
            is_array($value) ? $value : [],
        ));
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     */
    private function describe(FormField $field, string $key, array &$rules, array &$messages, array &$attributes): void
    {
        $rules[$key] = [...$this->presence($field), ...$this->typeRules($field), ...$field->rules];
        $attributes[$key] = $field->label->in();

        foreach ($field->messages as $rule => $message) {
            $messages[$key.'.'.$rule] = $message->in();
        }

        if (in_array($field->type, ['checkboxes', 'file'], true)) {
            $attributes[$key.'.*'] = $field->label->in();
        }

        match ($field->type) {
            'checkboxes' => $rules[$key.'.*'] = [Rule::in(array_keys($field->options))],
            // The name's extension, and content that fits it (ContentMatchesExtension); any file when the question takes any.
            'file' => $rules[$key.'.*'] = ['file', 'max:'.$field->maxKb, ...($field->allowedExtensions() === null ? [] : ['extensions:'.implode(',', $field->allowedExtensions()), new ContentMatchesExtension])],
            'group' => $this->describeRows($field, $key, $rules, $messages, $attributes),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     */
    private function describeRows(FormField $group, string $key, array &$rules, array &$messages, array &$attributes): void
    {
        foreach ($group->fields as $field) {
            $this->describe($field, $key.'.*.'.$field->name, $rules, $messages, $attributes);
        }
    }

    /**
     * @return list<string>
     */
    private function presence(FormField $field): array
    {
        if ($field->type === 'checkbox') {
            return $field->isRequired ? ['accepted'] : ['nullable', 'boolean'];
        }

        return [$field->isRequired ? 'required' : 'nullable'];
    }

    /**
     * @return list<mixed>
     */
    private function typeRules(FormField $field): array
    {
        return match ($field->type) {
            'text', 'tel' => ['string', 'max:'.($field->max ?? self::TEXT_MAX)],
            'textarea' => ['string', 'max:'.($field->max ?? self::TEXTAREA_MAX)],
            'email' => ['string', 'email', 'max:'.self::TEXT_MAX],
            'number' => array_filter(['numeric', $field->max === null ? null : 'max:'.$field->max]),
            'date' => ['date'],
            'select', 'radio' => [Rule::in(array_keys($field->options))],
            'checkboxes' => ['array'],
            'file' => ['array', 'max:'.$field->maxFiles],
            'group' => ['array', 'max:'.$field->maxRows],
            default => [],
        };
    }
}
