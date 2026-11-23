<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Seo\SeoSchema;

/**
 * Refuses a save of the SEO settings whose template mappings would make broken structured data,
 * with the error on the item's path so the editor shows it on the right block (ADR-0056). Runs
 * after pages.save, so the save rolls back.
 *
 * @internal
 */
final class MappingValidator
{
    public function check(mixed $page): void
    {
        if (! $page instanceof Page || $page->template()->name !== SeoSchema::SETTINGS_TEMPLATE) {
            return;
        }

        $errors = [];
        $seen = [];

        foreach (Mappings::items($page) as $index => $item) {
            $problems = $item['enabled'] ? $this->problems($item, $seen) : [];
            $seen[$item['template']] = true;

            if ($problems !== []) {
                $errors['values.'.SeoSchema::SCHEMAS.'.'.$index.'.'.SeoSchema::SCHEMA_MAP] = $problems;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array{template: string, type: string, lines: string}  $item
     * @param  array<string, true>  $seen
     * @return list<string>
     */
    private function problems(array $item, array $seen): array
    {
        $template = lynx()->templates->find($item['template']);

        if ($template === null || ! $template->hasUrl()) {
            return [Value::string(__('lynx-seo::validation.mapping.template', ['template' => $item['template']]))];
        }

        if (isset($seen[$item['template']])) {
            return [Value::string(__('lynx-seo::validation.mapping.twice', ['template' => $item['template']]))];
        }

        if (! SchemaTypes::has($item['type'])) {
            return [Value::string(__('lynx-seo::validation.mapping.not_offered', ['type' => $item['type'], 'types' => implode(', ', SchemaTypes::names())]))];
        }

        [$sources, $invalid] = Mapping::parse($item['lines']);
        $problems = array_map(fn (string $line): string => Value::string(__('lynx-seo::validation.mapping.line', ['line' => $line])), $invalid);
        $fields = lynx()->templates->fields($item['template']);

        foreach ($sources as $property => $source) {
            $problems[] = $this->problem($item['type'], $property, $source, $sources, $fields);
        }

        return array_values(array_filter($problems));
    }

    /**
     * @param  array<string, string>  $sources
     * @param  Collection<string, TemplateField>  $fields
     */
    private function problem(string $type, string $property, string $source, array $sources, Collection $fields): ?string
    {
        $kind = SchemaTypes::properties($type)[$property] ?? null;

        if ($kind === null) {
            return Value::string(__('lynx-seo::validation.mapping.no_property', ['type' => $type, 'property' => $property]));
        }

        if (Mapping::isConstant($source)) {
            return null;
        }

        $list = SchemaTypes::listOf($type, $property);

        if ($source === Mapping::ORGANIZATION) {
            return $kind === SchemaTypes::PAGE && $list === null ? null : Value::string(__('lynx-seo::validation.mapping.organization', ['source' => Mapping::ORGANIZATION]));
        }

        if (Mapping::isBuiltIn($source)) {
            return $list === null && in_array($source, Mapping::BUILT_INS, true) ? null : Value::string(__('lynx-seo::validation.mapping.built_in', ['source' => $source, 'sources' => implode(', ', Mapping::BUILT_INS)]));
        }

        return $this->fieldProblem($property, $kind, $source, $list, $sources, $fields);
    }

    /**
     * What is wrong with a field source: missing, or of a type the property cannot take.
     *
     * @param  array<string, string>  $sources
     * @param  Collection<string, TemplateField>  $fields
     */
    private function fieldProblem(string $property, string $kind, string $source, ?string $list, array $sources, Collection $fields): ?string
    {
        $field = $list === null ? $fields->get($source)?->field : collect(ListSubfields::of($fields->get($sources[$list] ?? '')?->field))->firstWhere('name', $source);

        if ($field === null) {
            return $list === null ? Value::string(__('lynx-seo::validation.mapping.no_field', ['field' => $source])) : Value::string(__('lynx-seo::validation.mapping.no_subfield', ['list' => $list, 'field' => $source]));
        }

        return in_array($field->type, SchemaTypes::FIELD_TYPES[$kind], true) ? null : Value::string(__('lynx-seo::validation.mapping.needs', ['property' => $property, 'kind' => Value::string(__("lynx-seo::validation.kinds.{$kind}")), 'field' => $source, 'type' => $field->type]));
    }
}
