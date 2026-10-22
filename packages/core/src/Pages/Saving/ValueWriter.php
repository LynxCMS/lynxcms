<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Saving;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\Items\StoresItems;
use LynxCms\Core\Fields\NeedsSavedPage;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Field values for pages.save: every changed value normalized and validated by its field type,
 * required fields checked in the default language, then the rows of each changed (field, language)
 * replaced, with where translations came from (ADR-0016).
 *
 * @internal
 */
final readonly class ValueWriter
{
    public function __construct(private TemplateRegistry $templates, private LanguageRegistry $languages) {}

    /**
     * The values to store, and errors keyed like the input ("values.price").
     *
     * @return array{0: array<string, array<int, mixed>>, 1: array<string, string>}
     */
    public function prepare(Page $page, Template $template, Language $default): array
    {
        $values = $page->rawValues();
        $errors = [];

        foreach ($page->dirtyValues() as $fieldName => $languageIds) {
            $templateField = $page->templateField($fieldName);

            if ($templateField === null) {
                $errors["values.{$fieldName}"] = "Template [{$template->name}] has no field [{$fieldName}].";

                continue;
            }

            foreach (array_keys($languageIds) as $languageId) {
                // A removed value is not in $values: it falls back and needs no checks.
                if (array_key_exists($languageId, $values[$fieldName] ?? [])) {
                    $errors += $this->normalize($templateField->field, $values[$fieldName][$languageId], "values.{$fieldName}");
                    $errors += $this->disallowedTypes($templateField, $values[$fieldName][$languageId], "values.{$fieldName}");
                }
            }
        }

        return [$values, $errors + $this->missingRequired($page, $template, $values, $default)];
    }

    /**
     * Replace the stored rows of every changed (field, language).
     *
     * @param  array<string, array<int, mixed>>  $values
     * @return list<string> what was written, e.g. "title [pt]" or "price"
     */
    public function write(int $pageId, Page $page, array $values, Input $input, CarbonInterface $now): array
    {
        $written = [];

        foreach ($page->dirtyValues() as $fieldName => $languageIds) {
            $field = ($page->templateField($fieldName) ?? throw new LogicException("Template has no field [{$fieldName}]."))->field;

            foreach (array_keys($languageIds) as $languageId) {
                $value = $values[$fieldName][$languageId] ?? null;
                $this->replaceRows($pageId, $field, $languageId, $value);

                if ($field->isTranslatable()) {
                    $this->recordOrigin($pageId, $field, $languageId, $value, $input, $now);
                }

                $written[] = $field->isTranslatable() ? "{$fieldName} [{$this->code($languageId)}]" : $fieldName;
            }
        }

        return $written;
    }

    /**
     * Normalizes in place; returns errors keyed under $key. A type's own error keys (an item
     * field's "2.role.pt") are appended to it.
     *
     * @return array<string, string>
     */
    private function normalize(Field $field, mixed &$value, string $key): array
    {
        $type = $field->fieldType();

        try {
            $normalized = $type->normalize($value, $field->config());
        } catch (ValidationException $exception) {
            if (! $type instanceof StoresItems) {
                return [$key => Value::string(collect($exception->errors())->flatten()->first())];
            }

            $errors = [];

            foreach ($exception->errors() as $path => $messages) {
                $errors[$path === '' ? $key : "{$key}.{$path}"] = Value::string(Value::list($messages)[0] ?? '');
            }

            return $errors;
        }

        $validator = Validator::make(['value' => $normalized], ['value' => $type->rules($field->config())]);

        if ($validator->fails()) {
            return [$key => $validator->errors()->first('value')];
        }

        $value = $normalized;

        return [];
    }

    /**
     * Matrix items whose type the template does not offer (template field setting allowed_types, ADR-0045).
     *
     * @return array<string, string>
     */
    private function disallowedTypes(TemplateField $templateField, mixed $value, string $key): array
    {
        $allowed = Value::strings($templateField->settings['allowed_types'] ?? []);

        if ($allowed === [] || ! $templateField->field->fieldType() instanceof StoresItems) {
            return [];
        }

        $errors = [];

        foreach (Value::list($value) as $index => $item) {
            $type = Value::string(Value::array($item)['type'] ?? '');

            if ($type !== '' && ! in_array($type, $allowed, true)) {
                $errors["{$key}.{$index}.type"] = "Blocks of type [{$type}] are not allowed here. Allowed: ".implode(', ', $allowed).'.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, array<int, mixed>>  $values
     * @return array<string, string>
     */
    private function missingRequired(Page $page, Template $template, array $values, Language $default): array
    {
        $fields = $this->templates->fields($template);
        $valueOf = fn (string $name): mixed => $values[$name][($fields[$name] ?? null)?->field->isTranslatable() ? $default->id : 0] ?? null;
        $errors = [];

        foreach ($fields as $fieldName => $templateField) {
            $value = $valueOf((string) $fieldName);

            if ($templateField->isRequired($valueOf) && ($value === null || $value === '' || $value === [])) {
                $errors["values.{$fieldName}"] = $this->requiredMessage($page, $templateField);
            }
        }

        return array_filter($errors, fn (string $message): bool => $message !== '');
    }

    /** Empty when the field may wait: a new unpublished page cannot hold files yet (NeedsSavedPage). */
    private function requiredMessage(Page $page, TemplateField $templateField): string
    {
        if (! $templateField->field->fieldType() instanceof NeedsSavedPage || $page->id !== null) {
            return "{$templateField->label()} is required in the default language.";
        }

        return ($page->status & PageStatus::UNPUBLISHED) !== 0
            ? ''
            : "{$templateField->label()} is required. Create the page unpublished, add it, then publish.";
    }

    private function replaceRows(int $pageId, Field $field, int $languageId, mixed $value): void
    {
        $type = $field->fieldType();
        $query = DB::table($field->table())->where('page_id', $pageId);
        $field->isTranslatable() ? $query->where('language_id', $languageId)->delete() : $query->delete();

        if ($value === null) {
            return;
        }

        foreach ($type->sleep($value, $field->config()) as $sort => $row) {
            DB::table($field->table())->insert(array_filter([
                'page_id' => $pageId,
                'language_id' => $field->isTranslatable() ? $languageId : null,
                'sort' => $type->multiple() ? $sort : null,
            ], fn (?int $column): bool => $column !== null) + $row);
        }
    }

    private function recordOrigin(int $pageId, Field $field, int $languageId, mixed $value, Input $input, CarbonInterface $now): void
    {
        $key = ['page_id' => $pageId, 'field_id' => $field->id, 'language_id' => $languageId];

        if ($value === null) {
            DB::table(Tables::FIELD_VALUE_META)->where($key)->delete();

            return;
        }

        DB::table(Tables::FIELD_VALUE_META)->updateOrInsert($key, [
            'origin' => $input->string('origin') ?? 'human',
            'provider' => $input->string('provider'),
            'source_hash' => $input->get("source_hashes.{$field->name}"),
            'updated_at' => $now,
        ]);
    }

    private function code(int $languageId): string
    {
        return $this->languages->find($languageId)->code ?? (string) $languageId;
    }
}
