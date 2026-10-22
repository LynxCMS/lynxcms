<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory as IconFactory;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;

/**
 * Builds the editor's view of a page's fields through the public API: each language's own value
 * or, when empty, the language whose value visitors see instead (ADR-0005, read-time fallback).
 *
 * @internal
 */
final class FieldViews
{
    /**
     * @return list<FieldView>
     */
    public function for(Page $page): array
    {
        $views = [];

        foreach (lynx()->templates->fields($page->template()->name) as $templateField) {
            $views[] = $this->view($templateField, $page);
        }

        return $views;
    }

    /**
     * Empty views of the given fields, for the New page screen.
     *
     * @param  array<string, TemplateField>  $fields
     * @return list<FieldView>
     */
    public function blank(array $fields): array
    {
        return array_values(array_map(fn (TemplateField $field): FieldView => $this->view($field, null), $fields));
    }

    private function view(TemplateField $templateField, ?Page $page): FieldView
    {
        $field = $templateField->field;
        $languages = $field->isTranslatable() ? lynx()->languages->all()->all() : [lynx()->languages->default()];
        $values = [];

        foreach ($languages as $language) {
            $values[Value::string($language->code)] = $page instanceof Page ? $this->value($page, $templateField, $language) : new LanguageValue(null, false);
        }

        $description = trim($templateField->description());
        $notes = trim($templateField->notes());

        return new FieldView(
            $templateField->name(),
            $templateField->label(),
            (string) $field->type,
            $field->isTranslatable(),
            $templateField->required(),
            $description === '' ? null : $description,
            $values,
            // A matrix may offer only some types on this template (ADR-0045).
            $field->config() + array_filter(['allowed_types' => $templateField->settings['allowed_types'] ?? null]),
            $notes === '' ? null : $notes,
            $page?->id,
            display: $templateField->editor(),
            width: $templateField->width(),
            placeholder: $templateField->placeholder() === '' ? null : $templateField->placeholder(),
            icon: $this->icon($templateField->icon()),
        );
    }

    /** The icon, when the admin has it: an unknown name shows no icon rather than breaking the editor. */
    private function icon(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        try {
            app(IconFactory::class)->svg('lucide-'.$name);

            return $name;
        } catch (SvgNotFound) {
            return null;
        }
    }

    private function value(Page $page, TemplateField $templateField, Language $language): LanguageValue
    {
        $name = $templateField->name();
        $type = lynx()->fieldTypes()->get((string) $templateField->field->type);
        $own = $page->getLanguageValue($language, $name);

        if (! $type->isEmpty($own) || ! $templateField->field->isTranslatable()) {
            return new LanguageValue($this->display($templateField, $own), ! $type->isEmpty($own));
        }

        foreach (lynx()->languages->chain($language) as $fallback) {
            $value = $page->getLanguageValue($fallback, $name);

            if ($fallback->id !== $language->id && ! $type->isEmpty($value)) {
                return new LanguageValue($this->display($templateField, $value), false, Value::string($fallback->name));
            }
        }

        return new LanguageValue(null, false);
    }

    /**
     * A stored value ready for the editor.value view: prepared by the type's converter when it
     * implements PreparesDisplay (page references become [id, title] pairs), so the view runs no queries.
     */
    public function display(TemplateField $templateField, mixed $value): mixed
    {
        return app(FieldInputs::class)->prepareDisplay((string) $templateField->field->type, $value);
    }
}
