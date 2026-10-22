<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Fields\NeedsSavedPage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;

/**
 * Form state for the page editor and the New page screen: values per field and language for
 * fields that have an input, and the "values" input for pages.save holding only what changed.
 * Fields and languages come from the template and the language list, never from the browser.
 *
 * @internal
 */
final readonly class EditorForm
{
    public function __construct(private FieldInputs $inputs, private FormValues $forms, private FieldConditions $conditions) {}

    /**
     * Fields of a page's template that have an input.
     *
     * @return array<string, TemplateField>
     */
    public function editableFields(string $template): array
    {
        return lynx()->templates->fields($template)
            ->filter(fn (TemplateField $field): bool => $this->inputs->for((string) $field->field->type) !== null && $field->editor()->isEditable())
            ->all();
    }

    /**
     * Locked and hidden fields (ADR-0063): never in the form, but conditions read their saved values.
     *
     * @return array<string, TemplateField>
     */
    public function readOnlyFields(string $template): array
    {
        return lynx()->templates->fields($template)
            ->filter(fn (TemplateField $field): bool => $this->inputs->for((string) $field->field->type) !== null && ! $field->editor()->isEditable())
            ->all();
    }

    /**
     * Fields a new page may need: the title, required fields, fields with required_if, and the
     * fields their conditions read, so the form can show or require them as it is filled (ADR-0030).
     * Fields that need a saved page (files) are left for the editor.
     *
     * @return array<string, TemplateField>
     */
    public function fieldsForNewPage(string $template): array
    {
        $watched = $this->conditions->watched(lynx()->templates->fields($template)->all());

        return array_filter($this->editableFields($template), fn (TemplateField $field): bool => ! $this->needsSavedPage($field)
            && ($field->name() === 'title'
            || $field->required()
            || $field->requiredIf() !== null
            || in_array($field->name(), $watched, true)));
    }

    /**
     * Required fields a new page cannot fill yet (NeedsSavedPage): it is created unpublished, and
     * they are added in the editor before publishing.
     *
     * @return list<string> labels
     */
    public function requiredAfterCreate(string $template): array
    {
        return array_values(array_map(
            fn (TemplateField $field): string => $field->label(),
            array_filter($this->editableFields($template), fn (TemplateField $field): bool => $this->needsSavedPage($field) && $field->required()),
        ));
    }

    private function needsSavedPage(TemplateField $field): bool
    {
        return $field->field->fieldType() instanceof NeedsSavedPage;
    }

    /**
     * Form values of a page for the given fields.
     *
     * @param  array<string, TemplateField>  $fields
     * @return array<string, array<string, mixed>> field name => language code => form value
     */
    public function initial(array $fields, ?Page $page = null): array
    {
        $values = [];

        foreach ($fields as $name => $templateField) {
            foreach ($this->codes($templateField) as $code) {
                $values[$name][$code] = $this->forms->toForm((string) $templateField->field->type, $page?->getLanguageValue($code, $name), $templateField->field);
            }
        }

        return $values;
    }

    /**
     * pages.save "values" for what differs between two form states: translatable fields as
     * {"code": value}, shared fields as a plain value.
     *
     * @param  array<string, TemplateField>  $fields
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $current
     * @return array<string, mixed>
     */
    public function changes(array $fields, array $original, array $current): array
    {
        $changes = [];

        foreach ($fields as $name => $templateField) {
            $changed = $this->changedLanguages($templateField, Value::array($original[$name] ?? []), Value::array($current[$name] ?? []));

            if ($changed === []) {
                continue;
            }

            // Translatable fields take {"code": value}; shared fields have one value.
            $changes[$name] = $templateField->field->isTranslatable() ? $changed : reset($changed);
        }

        return $changes;
    }

    /**
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $current
     * @return array<string, mixed> language code => new stored value, for languages that changed
     */
    private function changedLanguages(TemplateField $templateField, array $original, array $current): array
    {
        $type = (string) $templateField->field->type;
        $changed = [];

        foreach ($this->codes($templateField) as $code) {
            $after = $this->forms->fromForm($type, $current[$code] ?? null, $templateField->field);

            if ($this->forms->fromForm($type, $original[$code] ?? null, $templateField->field) !== $after) {
                $changed[$code] = $after;
            }
        }

        return $changed;
    }

    /**
     * @return list<string> every language for translatable fields, the default one for shared fields
     */
    private function codes(TemplateField $templateField): array
    {
        return $templateField->field->isTranslatable()
            ? Value::strings(lynx()->languages->all()->pluck('code')->all())
            : [Value::string(lynx()->languages->default()->code)];
    }
}
