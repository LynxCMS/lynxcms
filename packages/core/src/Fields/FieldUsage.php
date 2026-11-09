<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Where each field is used: on which templates, and inside the items of which repeaters and
 * matrices. For lynx:fields and lynx:doctor; it reads the schema only, never pages.
 *
 * @internal
 */
final readonly class FieldUsage
{
    public function __construct(private FieldRegistry $fields, private TemplateRegistry $templates) {}

    /**
     * @return array<string, list<string>> field name => names of the templates that have it, sorted
     */
    public function templates(): array
    {
        $usage = [];

        foreach ($this->templates->all()->sortBy('name') as $template) {
            foreach ($this->templates->fields($template)->keys() as $name) {
                $usage[(string) $name][] = (string) $template->name;
            }
        }

        return $usage;
    }

    /**
     * @return array<string, list<string>> field name => names of the repeaters and matrices whose items have it
     */
    public function items(): array
    {
        $usage = [];

        foreach ($this->fields->all()->sortBy('name') as $field) {
            $type = $field->fieldType();

            foreach ($type instanceof ItemFieldType ? $type->allSubfields($field->config()) : [] as $subfield) {
                $usage[$subfield->name()][] = (string) $field->name;
            }
        }

        return array_map(fn (array $owners): array => array_values(array_unique($owners)), $usage);
    }

    /**
     * Fields neither on a template nor inside items, sorted by name.
     *
     * @return list<string>
     */
    public function unused(): array
    {
        $used = $this->templates() + $this->items();

        return array_values($this->fields->all()
            ->map(fn ($field): string => (string) $field->name)
            ->reject(fn (string $name): bool => isset($used[$name]))
            ->sort()->all());
    }
}
