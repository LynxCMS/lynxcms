<?php

declare(strict_types=1);

namespace LynxCms\Core\View;

use LynxCms\Core\Fields\Types\MatrixType;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * The views matrix blocks render with (ADR-0045): {views_path}.fields.{field}.{template}.{type}
 * first, then {views_path}.fields.{field}.{type}. MatrixRenderer renders the first that exists;
 * StringCatalog reads the same ones for the strings they declare.
 *
 * @internal
 */
final readonly class BlockViews
{
    public function __construct(private TemplateRegistry $templates) {}

    /**
     * @return non-empty-list<string> view names, the one used first
     */
    public function candidates(string $field, ?string $template, string $type): array
    {
        $base = Value::string(config('lynx.views_path', 'templates'), 'templates').'.fields.'.$field;

        return $template === null ? ["{$base}.{$type}"] : ["{$base}.{$template}.{$type}", "{$base}.{$type}"];
    }

    /**
     * The candidates of every block type a template offers in its matrix fields (all the field's
     * types, or those its allowed_types setting names).
     *
     * @return list<non-empty-list<string>>
     */
    public function forTemplate(string $template): array
    {
        if ($this->templates->find($template) === null) {
            return [];
        }

        $blocks = [];

        foreach ($this->templates->fields($template) as $name => $templateField) {
            foreach ($this->offeredTypes($templateField) as $type) {
                $blocks[] = $this->candidates((string) $name, $template, $type);
            }
        }

        return $blocks;
    }

    /** @return list<string> */
    private function offeredTypes(TemplateField $templateField): array
    {
        $type = $templateField->field->fieldType();

        if (! $type instanceof MatrixType) {
            return [];
        }

        $slugs = $type->slugs($templateField->field->config());
        $allowed = Value::strings($templateField->settings['allowed_types'] ?? []);

        return $allowed === [] ? $slugs : array_values(array_intersect($slugs, $allowed));
    }
}
