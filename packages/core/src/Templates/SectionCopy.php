<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\Layout\LayoutTab;

/**
 * A template's section as templates.copy-section takes it (ADR-0108): the section's stored
 * settings, its fields' settings on that template in order, and the definition of its tab.
 * entry() gives it back as a field list entry for templates.update, with fields renamed for a
 * template that already has them.
 *
 * @internal
 */
final readonly class SectionCopy
{
    private const array CONDITION_KEYS = ['show_if', 'required_if'];

    /**
     * @param  array<string, mixed>  $section  key, label, tab, column, collapsed
     * @param  array<string, array<string, mixed>>  $fields  field name => its settings on the template
     * @param  array<string, mixed>|null  $tab  the section's tab, unless it is Content
     */
    private function __construct(public array $section, public array $fields, public ?array $tab) {}

    public static function of(Template $template, string $key, TemplateRegistry $templates): self
    {
        $config = $template->config();
        $section = self::find(Value::list($config['sections'] ?? []), $key)
            ?? throw ValidationException::withMessages(['section' => "Template [{$template->name}] has no section [{$key}]."]);

        $fields = $templates->fields($template)
            ->filter(fn (TemplateField $field): bool => ($field->settings['section'] ?? null) === $key)
            ->map(fn (TemplateField $field): array => array_diff_key($field->settings, ['section' => true]))
            ->all();

        $tab = Value::string($section['tab'] ?? LayoutTab::CONTENT);

        return new self($section, $fields, self::find(Value::list($config['tabs'] ?? []), $tab));
    }

    public function key(): string
    {
        return Value::string($this->section['key']);
    }

    /**
     * @return list<string>
     */
    public function fieldNames(): array
    {
        return array_keys($this->fields);
    }

    /**
     * The section as templates.update reads it, its fields (and the conditions that read them) renamed.
     *
     * @param  array<string, string>  $names  field name => the name it takes on the target template
     * @return array<string, mixed>
     */
    public function entry(array $names): array
    {
        $fields = [];

        foreach ($this->fields as $name => $settings) {
            $fields[] = ['name' => $names[$name] ?? $name] + $this->renameConditions($settings, $names);
        }

        return ['section' => $this->key()] + array_diff_key($this->section, ['key' => true]) + ['fields' => $fields];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $names
     * @return array<string, mixed>
     */
    private function renameConditions(array $settings, array $names): array
    {
        foreach (self::CONDITION_KEYS as $key) {
            $condition = trim(Value::string($settings[$key] ?? ''));

            if ($condition !== '' && $names !== []) {
                $settings[$key] = FieldCondition::parse($condition)->renamed($names)->toString();
            }
        }

        return $settings;
    }

    /**
     * @param  list<mixed>  $entries  sections or tabs as stored in a template's settings
     * @return array<string, mixed>|null
     */
    private static function find(array $entries, string $key): ?array
    {
        foreach ($entries as $entry) {
            if (is_array($entry) && ($entry['key'] ?? null) === $key) {
                return Value::map($entry);
            }
        }

        return null;
    }
}
