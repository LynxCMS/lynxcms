<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Illuminate\Support\Str;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * Reads one template of a draft (ADR-0116) into a schema file's template item, without its uuid.
 * Family keys become settings (url is has_url, children and parents the family rules), field
 * entries become the field list schema files write: "name", "name*" for a required field,
 * {name: shorthand} for a field defined in place, {name: ..., label: ...} for settings of this
 * use, and {section: ..., fields: [...]} for a section.
 *
 * @internal
 */
final class DraftTemplates
{
    public const array KEYS = ['label', 'url', 'singleton', 'children', 'parents', 'allowed', 'tabs', 'settings', 'fields'];

    private const array SECTION_KEYS = ['tab', 'column', 'collapsed'];

    private const string SECTION_KEY_PATTERN = '/^[a-z][a-z0-9_-]{0,63}$/';

    /** @var array<string, mixed> fields defined in place, by name */
    private array $inline = [];

    /**
     * @param  array<string, mixed>  $definition
     * @param  string|array<string, string>|null  $label  the label when the draft gives none: the site's, for a template it has
     * @return array{template: array{name: string, label: string|array<string, string>, settings: array<string, mixed>, fields: list<mixed>}, inline: array<string, mixed>}
     */
    public function compile(string $name, array $definition, string|array|null $label = null): array
    {
        foreach (array_keys($definition) as $key) {
            if (! in_array($key, self::KEYS, true)) {
                throw new DraftError("Template [{$name}] has an unknown key [{$key}].".DraftError::suggestion((string) $key, self::KEYS).' Its keys: '.implode(', ', self::KEYS).'.');
            }
        }

        $this->inline = [];
        $fields = array_map(fn (mixed $entry): mixed => $this->entry($name, $entry), Value::list($definition['fields'] ?? []));

        return ['template' => [
            'name' => $name,
            'label' => isset($definition['label']) ? LocalizedText::from($definition['label'])->toStorage() : ($label ?? DraftShorthand::label($name)),
            'settings' => $this->settings($definition),
            'fields' => $fields,
        ], 'inline' => $this->inline];
    }

    /**
     * The field names in a template's field list, sections' fields included.
     *
     * @param  list<mixed>  $entries
     * @return list<string>
     */
    public static function fieldNames(array $entries): array
    {
        $names = [];

        foreach ($entries as $entry) {
            $entry = is_string($entry) ? ['name' => $entry] : Value::map($entry);
            array_push($names, ...(isset($entry['section']) ? self::fieldNames(Value::list($entry['fields'] ?? [])) : [Value::string($entry['name'] ?? '')]));
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function settings(array $definition): array
    {
        // "settings" holds what the keys below don't express, as schema files write it.
        $settings = Value::map($definition['settings'] ?? []);
        if (isset($definition['url'])) {
            $settings['has_url'] = Value::bool($definition['url']);
        }
        if (isset($definition['singleton'])) {
            $settings['singleton'] = Value::bool($definition['singleton']);
        }

        if (isset($definition['children'])) {
            $settings['allow_children'] = true;
            $settings['child_templates'] = Value::strings($definition['children']);
        }

        if (isset($definition['parents'])) {
            $settings['parent_templates'] = Value::strings($definition['parents']);
        }
        if (isset($definition['allowed'])) {
            $settings['allowed_templates'] = Value::strings($definition['allowed']);
        }
        if (isset($definition['tabs'])) {
            $settings['tabs'] = $this->tabs(Value::array($definition['tabs']));
        }

        return $settings;
    }

    /**
     * Tabs as {key: label}, or as the list schema files write.
     *
     * @param  array<array-key, mixed>  $tabs
     * @return list<array<string, mixed>>
     */
    private function tabs(array $tabs): array
    {
        if (array_is_list($tabs)) {
            return array_map(Value::map(...), $tabs);
        }

        return array_map(fn (string $key, mixed $label): array => ['key' => $key, 'label' => $label], array_map(strval(...), array_keys($tabs)), $tabs);
    }

    private function entry(string $template, mixed $entry, bool $inSection = false): mixed
    {
        if (is_string($entry)) {
            return $this->named($entry);
        }

        $entry = Value::map($entry);

        return match (true) {
            isset($entry['section']) && ! $inSection => $this->section($template, $entry),
            isset($entry['section']) => throw new DraftError("Template [{$template}]: sections can't hold sections."),
            isset($entry['name']) => $entry,
            count($entry) === 1 => $this->inlineField(array_key_first($entry), reset($entry)),
            default => throw new DraftError("Template [{$template}]: a field entry is a name, {name: type}, {name: ..., required: true} or a section."),
        };
    }

    private function inlineField(string $name, mixed $definition): mixed
    {
        $entry = $this->named($name);
        $this->inline[is_string($entry) ? $entry : Value::string(Value::map($entry)['name'] ?? '')] = $definition;

        return $entry;
    }

    /** "title" stays a name, "title*" is the field required on this template. */
    private function named(string $entry): mixed
    {
        $field = DraftShorthand::itemField($entry);

        return isset($field['required']) ? $field : $entry;
    }

    /**
     * A section by key or by label ("Extra details" is the key extra-details), with its fields.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function section(string $template, array $entry): array
    {
        $given = Value::string($entry['section']);
        $isKey = preg_match(self::SECTION_KEY_PATTERN, $given) === 1;
        $label = $entry['label'] ?? ($isKey ? DraftShorthand::label($given) : $given);

        return ['section' => $isKey ? $given : Str::slug($given), 'label' => $label]
            + array_intersect_key($entry, array_flip(self::SECTION_KEYS))
            + ['fields' => array_map(fn (mixed $field): mixed => $this->entry($template, $field, inSection: true), Value::list($entry['fields'] ?? []))];
    }
}
