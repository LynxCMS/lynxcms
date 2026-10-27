<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Fills in the defaults of field types that have one (HasDefaultValue) where new content leaves a
 * field out (ADR-0088). A field given as empty stays empty, and existing content is never filled.
 *
 * @internal
 */
final readonly class DefaultValues
{
    public function __construct(private TemplateRegistry $templates) {}

    public function fillNewPage(Page $page, Language $language): void
    {
        $given = $page->dirtyValues();

        foreach ($this->templates->fields($page->templateId) as $name => $templateField) {
            $default = self::of($templateField->field);

            if ($default !== null && ! array_key_exists((string) $name, $given)) {
                $page->setLanguageValue($language, (string) $name, $default);
            }
        }
    }

    /**
     * @param  list<Subfield>  $subfields
     * @param  array<string, mixed>  $values  a new item's values, field name => value
     * @return array<string, mixed>
     */
    public static function fillNewItem(array $subfields, array $values): array
    {
        foreach ($subfields as $subfield) {
            $default = self::of($subfield->field);

            if ($default !== null && ! array_key_exists($subfield->name(), $values)) {
                $values[$subfield->name()] = $default;
            }
        }

        return $values;
    }

    private static function of(Field $field): mixed
    {
        $type = $field->fieldType();

        return $type instanceof HasDefaultValue ? $type->defaultValue($field->config()) : null;
    }
}
