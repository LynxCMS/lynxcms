<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Schema\SchemaExporter;
use LynxCms\Core\Schema\SchemaOwners;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes the site's own schema as a draft (lynx:draft --from-site, ADR-0116): each field in the
 * short form when that form reads back as the same field, in the long form otherwise; templates
 * with their family keys, starred required fields and sections. What Lynx and the modules own is
 * left out. Building the draft it writes changes nothing.
 *
 * @phpstan-import-type FieldItem from SchemaSync
 *
 * @api
 */
final readonly class DraftWriter
{
    private const string HEADER = DraftJsonSchema::MODELINE."\n# The site's own templates and fields, written by php artisan lynx:draft --from-site.\n\n";

    /** Yaml::dump nesting from which lists and maps are written inline. */
    private const int INLINE_LEVEL = 4;

    private const int INDENT = 2;

    public function __construct(
        private FieldRegistry $fields,
        private TemplateRegistry $templates,
        private SchemaExporter $exporter,
        private SchemaOwners $owners,
        private DraftShorthand $shorthand,
        private DraftValues $values,
    ) {}

    public function write(): string
    {
        $fields = $this->fields->all()->reject(fn (Field $f): bool => $this->owners->isSystemField((string) $f->name))->sortBy('name');
        $templates = $this->templates->all()->reject(fn (Template $t): bool => $this->owners->isSystemTemplate((string) $t->name))->sortBy('name');

        $draft = [
            'fields' => $fields->mapWithKeys(fn (Field $f): array => [(string) $f->name => $this->field($this->exporter->fieldData($f))])->all(),
            'templates' => $templates->mapWithKeys(fn (Template $t): array => [(string) $t->name => $this->template($t)])->all(),
        ];

        return self::HEADER.Yaml::dump(array_filter($draft), self::INLINE_LEVEL, self::INDENT);
    }

    /**
     * @param  FieldItem  $item
     */
    private function field(array $item): mixed
    {
        $short = $this->values->shortForm($item);

        if ($short !== null && $this->readsBackAs($short, $item)) {
            return $short;
        }

        return $this->longForm($item);
    }

    /**
     * @param  FieldItem  $item
     */
    private function readsBackAs(mixed $definition, array $item): bool
    {
        try {
            $field = $this->shorthand->field($item['name'], $definition);
        } catch (DraftError) {
            return false;
        }

        return $field['type'] === $item['type'] && $field['translatable'] === $item['translatable']
            && $field['label'] === $item['label'] && $field['settings'] == $item['settings'];
    }

    /**
     * @param  FieldItem  $item
     * @return array<string, mixed>
     */
    private function longForm(array $item): array
    {
        $settings = $item['settings'];

        if (isset($settings['fields'])) {
            $settings['fields'] = array_map($this->itemEntry(...), Value::list($settings['fields']));
        }

        if (isset($settings['types'])) {
            $settings['types'] = array_map(fn (mixed $type): array => [...array_diff_key(Value::map($type), ['fields' => true]), 'fields' => array_map($this->itemEntry(...), Value::list(Value::map($type)['fields'] ?? []))], Value::list($settings['types']));
        }

        return ['type' => $item['type']]
            + ($item['label'] !== DraftShorthand::label($item['name']) ? ['label' => $item['label']] : [])
            + ($item['translatable'] !== $this->shorthand->translatesByDefault($item['type']) ? ['translatable' => $item['translatable']] : [])
            + $settings;
    }

    /** An item field as "name" or "name*" when it has no other settings. */
    private function itemEntry(mixed $entry): mixed
    {
        $entry = Value::map($entry);
        $name = Value::string($entry['name'] ?? '');

        return match ($entry) {
            ['name' => $name] => $name,
            ['name' => $name, 'required' => true] => $name.'*',
            default => $entry,
        };
    }

    /** @return array<string, mixed> */
    private function template(Template $template): array
    {
        $item = $this->exporter->templateData($template);
        $draft = $item['label'] !== DraftShorthand::label($item['name']) ? ['label' => $item['label']] : [];

        return $draft + $this->familyKeys(Value::map($item['settings'])) + ['fields' => array_map($this->templateEntry(...), $item['fields'])];
    }

    /**
     * The settings as the draft's template keys; what they can't say stays under "settings".
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function familyKeys(array $settings): array
    {
        $keys = [];
        $take = function (string $setting, string $key) use (&$settings, &$keys): void {
            if (array_key_exists($setting, $settings)) {
                $keys[$key] = $settings[$setting];
                unset($settings[$setting]);
            }
        };

        $take('has_url', 'url');
        $take('singleton', 'singleton');

        if (($settings['allow_children'] ?? null) === true && Value::strings($settings['child_templates'] ?? []) !== []) {
            unset($settings['allow_children']);
            $take('child_templates', 'children');
        }

        $take('parent_templates', 'parents');
        $take('allowed_templates', 'allowed');
        $tabs = $this->simpleTabs($settings['tabs'] ?? null);

        if ($tabs !== null) {
            $keys['tabs'] = $tabs;
            unset($settings['tabs']);
        }

        return $keys + ($settings !== [] ? ['settings' => $settings] : []);
    }

    /**
     * Tabs as {key: label}, when each has only a key and a plain label.
     *
     * @return array<string, string>|null
     */
    private function simpleTabs(mixed $tabs): ?array
    {
        $simple = [];

        foreach (Value::list($tabs) as $tab) {
            $tab = Value::map($tab);

            if (array_keys($tab) !== ['key', 'label'] || ! is_string($tab['label'])) {
                return null;
            }

            $simple[Value::string($tab['key'])] = $tab['label'];
        }

        return $simple === [] ? null : $simple;
    }

    private function templateEntry(mixed $entry): mixed
    {
        if (is_string($entry)) {
            return $entry;
        }

        $entry = Value::map($entry);

        if (! isset($entry['section'])) {
            return $this->itemEntry($entry);
        }

        $key = Value::string($entry['section']);
        $label = $entry['label'] ?? null;

        return ['section' => $key]
            + ($label !== null && $label !== DraftShorthand::label($key) ? ['label' => $label] : [])
            + array_diff_key($entry, ['section' => true, 'label' => true, 'fields' => true])
            + ['fields' => array_map($this->templateEntry(...), Value::list($entry['fields'] ?? []))];
    }
}
