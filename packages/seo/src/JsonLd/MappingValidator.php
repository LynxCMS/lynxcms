<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;
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
    private const array KIND_NAMES = [
        SchemaTypes::IMAGE => 'an image field',
        SchemaTypes::DATE => 'a date field',
        SchemaTypes::NUMBER => 'a number field',
        SchemaTypes::PAGE => 'a page reference field',
        SchemaTypes::LIST => 'a repeater or matrix field',
        SchemaTypes::ENUM => 'a choice or text field',
        SchemaTypes::LINES => 'a text field',
        SchemaTypes::URL => 'a URL, text or page reference field',
        SchemaTypes::TEXT => 'a text field',
    ];

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
            return ["Template [{$item['template']}] does not exist or has no URL."];
        }

        if (isset($seen[$item['template']])) {
            return ["Template [{$item['template']}] is already mapped above."];
        }

        if (! SchemaTypes::has($item['type'])) {
            return ["[{$item['type']}] is not a type structured data offers: use one of ".implode(', ', SchemaTypes::names()).'.'];
        }

        [$sources, $invalid] = Mapping::parse($item['lines']);
        $problems = array_map(fn (string $line): string => "Line [{$line}]: write property: source.", $invalid);
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
            return "{$type} has no property [{$property}].";
        }

        if (Mapping::isConstant($source)) {
            return null;
        }

        $list = SchemaTypes::listOf($type, $property);

        if ($source === Mapping::ORGANIZATION) {
            return $kind === SchemaTypes::PAGE && $list === null ? null : '['.Mapping::ORGANIZATION.'] only fits properties that take a page, such as author, provider or organizer.';
        }

        if (Mapping::isBuiltIn($source)) {
            return $list === null && in_array($source, Mapping::BUILT_INS, true) ? null : "[{$source}] is not a built-in source here: use ".implode(', ', Mapping::BUILT_INS).'.';
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
            return $list === null ? "The template has no field [{$source}]." : "The [{$list}] list has no subfield [{$source}].";
        }

        return in_array($field->type, SchemaTypes::FIELD_TYPES[$kind], true) ? null : "Property [{$property}] needs ".self::KIND_NAMES[$kind]."; [{$source}] is a {$field->type} field.";
    }
}
