<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Layout;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;

/**
 * Reads a template's field list as actions and schema files give it: field names, field objects
 * and section entries ({"section": "hero", "label": "Hero", "fields": [...]}). Sections are
 * flattened: their fields join the list in place, marked with the section's key.
 *
 * @internal
 */
final readonly class FieldListInput
{
    private const array SECTION_KEYS = ['label', 'tab', 'column', 'collapsed'];

    /**
     * @param  list<mixed>  $fields  the flat field specs, sections' fields marked with "section"
     * @param  list<array<string, mixed>>  $sections  key, label, tab, column, collapsed
     */
    private function __construct(public array $fields, public array $sections) {}

    /**
     * @param  array<mixed>  $specs
     */
    public static function parse(array $specs): self
    {
        $fields = [];
        $sections = [];

        foreach ($specs as $spec) {
            if (! is_array($spec) || ! isset($spec['section'])) {
                $fields[] = $spec;

                continue;
            }

            $section = self::section(Value::map($spec), array_column($sections, 'key'));
            $sections[] = $section;
            $key = Value::string($section['key']);

            foreach (Value::list($spec['fields'] ?? []) as $field) {
                $fields[] = self::inSection($field, $key);
            }
        }

        return new self($fields, $sections);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<mixed>  $seen
     * @return array<string, mixed>
     */
    private static function section(array $spec, array $seen): array
    {
        $key = Value::string($spec['section']);

        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $key) !== 1) {
            throw ValidationException::withMessages(['fields' => "The section key [{$key}] must be lowercase letters, digits, - and _, starting with a letter."]);
        }

        if (in_array($key, $seen, true)) {
            throw ValidationException::withMessages(['fields' => "The section [{$key}] appears twice."]);
        }

        return ['key' => $key] + array_intersect_key($spec, array_flip(self::SECTION_KEYS)) + ['label' => $key];
    }

    /** A field of a section, marked with it; its tab and column come from the section. */
    private static function inSection(mixed $field, string $section): mixed
    {
        $spec = is_string($field) ? ['name' => $field] : Value::map($field);

        if (isset($spec['section'])) {
            $nested = Value::string($spec['section']);
            throw ValidationException::withMessages(['fields' => "Sections cannot be inside sections: [{$nested}] is inside [{$section}]."]);
        }

        if (isset($spec['tab']) || isset($spec['column'])) {
            $name = Value::string($spec['name'] ?? '');
            throw ValidationException::withMessages(['fields' => "Field [{$name}] is in section [{$section}]: set the tab and column on the section."]);
        }

        return ['section' => $section] + $spec;
    }
}
