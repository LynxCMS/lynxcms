<?php

declare(strict_types=1);

namespace LynxCms\Seo\Admin;

use LynxCms\Core\Models\Field;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Seo\JsonLd\ListSubfields;
use LynxCms\Seo\JsonLd\Mapping;
use LynxCms\Seo\JsonLd\SchemaTypes;

/**
 * Sources that fit a property: the template's fields of a matching type and the built-ins, or,
 * for a list property's sub-properties, the subfields of the repeater chosen for the list.
 *
 * @internal
 */
final readonly class SourceSuggestions
{
    /** Built-ins by the kinds they fit. */
    private const array BUILT_INS = [
        SchemaTypes::TEXT => ['@title'],
        SchemaTypes::URL => ['@url'],
        SchemaTypes::DATE => ['@published', '@modified'],
        SchemaTypes::PAGE => [Mapping::ORGANIZATION],
    ];

    public function __construct(private string $template, private string $type) {}

    /**
     * @param  string|null  $listSource  the field chosen for the list a sub-property belongs to
     * @return list<string>
     */
    public function for(string $kind, ?string $listSource): array
    {
        $types = SchemaTypes::FIELD_TYPES[$kind] ?? [];
        $fields = $listSource === null ? $this->templateFields() : ListSubfields::of($this->templateFields()[$listSource] ?? null);
        $names = array_values(array_map(fn (Field $field): string => $field->name, array_filter($fields, fn (Field $field): bool => in_array($field->type, $types, true))));

        return $listSource === null ? [...$names, ...self::BUILT_INS[$kind] ?? []] : $names;
    }

    /** @return array<string, Field> */
    private function templateFields(): array
    {
        if ($this->type === '' || lynx()->templates->find($this->template) === null) {
            return [];
        }

        return lynx()->templates->fields($this->template)->map(fn (TemplateField $field): Field => $field->field)->all();
    }
}
