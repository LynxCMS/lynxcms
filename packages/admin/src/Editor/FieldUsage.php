<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;

/**
 * Which templates use each field, directly or as a subfield of a repeater or matrix they use, for
 * the field screens.
 *
 * @internal
 */
final class FieldUsage
{
    /**
     * @return array<string, list<array{template: string, via: string|null}>> field name => uses, sorted by template
     */
    public function all(): array
    {
        $uses = [];

        foreach (lynx()->templates->all() as $template) {
            foreach (lynx()->templates->fields($template) as $name => $templateField) {
                $uses[Value::string($name)][] = ['template' => (string) $template->name, 'via' => null];

                foreach ($this->subfieldNames($templateField) as $subfield) {
                    $uses[$subfield][] = ['template' => (string) $template->name, 'via' => Value::string($name)];
                }
            }
        }

        return array_map(function (array $list): array {
            usort($list, fn (array $a, array $b): int => [$a['template'], $a['via'] ?? ''] <=> [$b['template'], $b['via'] ?? '']);

            return $list;
        }, $uses);
    }

    /**
     * "course", or "course (in credits)" for a use through a repeater or matrix.
     *
     * @return array<string, list<string>> field name => labels
     */
    public function labels(): array
    {
        return array_map(
            fn (array $list): array => array_values(array_unique(array_map(fn (array $use): string => $use['template'].($use['via'] === null ? '' : " (in {$use['via']})"), $list))),
            $this->all(),
        );
    }

    /**
     * @return list<string> names of the templates using the field either way
     */
    public function templatesOf(string $field): array
    {
        return array_values(array_unique(array_column($this->all()[$field] ?? [], 'template')));
    }

    /**
     * @return list<string>
     */
    private function subfieldNames(TemplateField $templateField): array
    {
        $type = $templateField->field->fieldType();

        return $type instanceof ItemFieldType
            ? array_map(fn (Subfield $subfield): string => $subfield->name(), $type->allSubfields($templateField->field->config()))
            : [];
    }
}
