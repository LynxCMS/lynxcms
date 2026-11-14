<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Illuminate\Support\Str;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Schema\SchemaOwners;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Compiles a draft (ADR-0116) into the items schema files hold. Everything apply would refuse is
 * refused here first, naming the place in the draft: unknown types and settings, fields nobody
 * defines, a repeater inside a repeater, a change of a field's type, and schema a module owns.
 *
 * For what the site has, a draft adds and changes, and never removes: a label the draft doesn't
 * give stays the site's, and settings it doesn't name keep their values. Building the same draft
 * again changes nothing.
 *
 * @phpstan-import-type FieldItem from SchemaSync
 * @phpstan-import-type TemplateItem from SchemaSync
 *
 * @api
 */
final readonly class DraftCompiler
{
    public const array KEYS = ['fields', 'templates', 'pages'];

    /** Template settings that come from the field list, not from the settings themselves. */
    private const array DERIVED_TEMPLATE_SETTINGS = ['sections' => true];

    public function __construct(
        private DraftShorthand $shorthand,
        private FieldRegistry $fields,
        private TemplateRegistry $templates,
        private SchemaOwners $owners,
        private DraftUseSettings $useSettings,
        private DraftPages $pages,
        private DraftChoiceOptions $choices,
    ) {}

    /**
     * @param  array<array-key, mixed>  $draft  the parsed draft.yaml
     *
     * @throws DraftError
     */
    public function compile(array $draft): CompiledDraft
    {
        $this->assertKeys($draft);
        $fields = [];

        foreach (Value::map($draft['fields'] ?? []) as $name => $definition) {
            $fields = $this->define($fields, (string) $name, $definition);
        }

        $templates = [];

        foreach (Value::map($draft['templates'] ?? []) as $name => $definition) {
            ['template' => $templates[], 'inline' => $inline] = (new DraftTemplates)->compile((string) $name, Value::map($definition), $this->templates->find((string) $name)?->labels()->toStorage());

            foreach ($inline as $field => $inlineDefinition) {
                $fields = $this->define($fields, $field, $inlineDefinition);
            }
        }

        $this->check(array_values($fields), $templates);
        $fieldItems = array_map($this->withFieldUuid(...), array_values($fields));
        $templateItems = array_map($this->withTemplateUuid(...), $templates);

        return new CompiledDraft($fieldItems, $templateItems, $this->pages->compile(Value::map($draft['pages'] ?? []), $fieldItems, $templateItems));
    }

    /** @param  array<array-key, mixed>  $draft */
    private function assertKeys(array $draft): void
    {
        foreach (array_keys($draft) as $key) {
            if (! in_array($key, self::KEYS, true)) {
                throw new DraftError("A draft has fields, templates and pages, not [{$key}].".DraftError::suggestion((string) $key, self::KEYS));
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, array<string, mixed>>
     */
    private function define(array $fields, string $name, mixed $definition): array
    {
        $existing = $this->fields->find($name);
        $field = $this->shorthand->field($name, $definition, $existing?->labels()->toStorage(), $existing?->isTranslatable());

        if (isset($fields[$name]) && $fields[$name] != $field) {
            throw new DraftError("Field [{$name}] is defined twice, in different ways. Define it once, under fields or in one template.");
        }

        $fields[$name] = $field;

        return $fields;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array<string, mixed>>  $templates
     */
    private function check(array $fields, array $templates): void
    {
        $defined = array_column($fields, null, 'name');

        foreach ($fields as $field) {
            $this->checkField($field, $defined);
        }

        $templateNames = array_column($templates, 'name');

        foreach ($templates as $template) {
            $name = Value::string($template['name']);
            $this->assertNotOwned('Template', $name, $this->owners->ownerOfTemplate($name));
            $this->assertFieldsExist("Template [{$name}]", DraftTemplates::fieldNames(Value::list($template['fields'])), $defined);
            $this->assertTemplatesExist($name, Value::map($template['settings']), $templateNames);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<array-key, array<string, mixed>>  $defined
     */
    private function checkField(array $field, array $defined): void
    {
        $name = Value::string($field['name']);
        $this->assertNotOwned('Field', $name, $this->owners->ownerOfField($name));
        $existing = $this->fields->find($name);

        $makesShared = $existing instanceof Field && $existing->isTranslatable() && ! $field['translatable'];

        if ($existing instanceof Field && ($existing->type !== $field['type'] || $makesShared)) {
            throw new DraftError("Field [{$name}] is ".$this->article($existing->type)." {$existing->type} field in the site, and the draft makes it ".Value::string($field['type']).($makesShared ? ' with translatable changed' : '').". Schema sync can't change a field's type or make a translatable field shared.");
        }

        $items = $this->itemFieldNames(Value::map($field['settings']));
        $this->assertFieldsExist("Field [{$name}]", $items, $defined);

        foreach ($items as $item) {
            if ($this->holdsFields(Value::map($defined[$item]['settings'] ?? $this->fields->find($item)?->config() ?? []))) {
                throw new DraftError("Field [{$name}]: [{$item}] holds item fields itself, and a repeater or matrix can't list another one. Schema sync creates item fields one level deep.");
            }
        }
    }

    private function assertNotOwned(string $kind, string $name, ?string $owner): void
    {
        if ($owner !== null) {
            throw new DraftError("{$kind} [{$name}] belongs to {$owner}, so a draft can't define it. Use another name.");
        }
    }

    /**
     * @param  list<string>  $names
     * @param  array<array-key, array<string, mixed>>  $defined
     */
    private function assertFieldsExist(string $user, array $names, array $defined): void
    {
        foreach ($names as $field) {
            if (! isset($defined[$field]) && ! $this->fields->find($field) instanceof Field) {
                throw new DraftError("{$user} uses the field [{$field}], which neither the draft nor the site defines.".DraftError::suggestion($field, array_keys($defined)));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<mixed>  $draftTemplates
     */
    private function assertTemplatesExist(string $template, array $settings, array $draftTemplates): void
    {
        $known = [...Value::strings($draftTemplates), ...$this->templates->all()->map(fn (Template $t): string => (string) $t->name)->values()->all()];

        foreach (['child_templates', 'parent_templates', 'allowed_templates'] as $key) {
            foreach (Value::strings($settings[$key] ?? []) as $name) {
                if (! in_array($name, $known, true)) {
                    throw new DraftError("Template [{$template}]: there is no template [{$name}].".DraftError::suggestion($name, $known));
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function itemFieldNames(array $settings): array
    {
        $lists = [Value::list($settings['fields'] ?? []), ...array_map(fn (mixed $type): array => Value::list(Value::map($type)['fields'] ?? []), Value::list($settings['types'] ?? []))];

        return array_values(array_map(fn (mixed $item): string => Value::string(Value::map($item)['name'] ?? ''), array_merge(...$lists)));
    }

    /** @param  array<string, mixed>  $settings */
    private function holdsFields(array $settings): bool
    {
        return isset($settings['fields']) || isset($settings['types']);
    }

    private function article(string $word): string
    {
        return in_array($word[0] ?? '', ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';
    }

    /**
     * @param  array<string, mixed>  $field
     * @return FieldItem
     */
    private function withFieldUuid(array $field): array
    {
        $existing = $this->fields->find(Value::string($field['name']));
        $settings = Value::map($field['settings']);

        if (isset($settings['options'], $existing?->config()['options'])) {
            $settings['options'] = $this->choices->keepTranslations(Value::list($settings['options']), Value::list($existing->config()['options']));
        }

        return [
            'uuid' => $existing->uuid ?? (string) Str::uuid(),
            'name' => Value::string($field['name']),
            'type' => Value::string($field['type']),
            'label' => LocalizedText::from($field['label'])->toStorage(),
            'translatable' => Value::bool($field['translatable']),
            'settings' => $existing instanceof Field ? [...$existing->config(), ...$settings] : $settings,
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @return TemplateItem
     */
    private function withTemplateUuid(array $template): array
    {
        $existing = $this->templates->find(Value::string($template['name']));
        $settings = Value::map($template['settings']);

        return [
            'uuid' => $existing->uuid ?? (string) Str::uuid(),
            'name' => Value::string($template['name']),
            'label' => LocalizedText::from($template['label'])->toStorage(),
            'settings' => $existing instanceof Template ? [...array_diff_key($existing->config(), self::DERIVED_TEMPLATE_SETTINGS), ...$settings] : $settings,
            'fields' => $this->useSettings->merge($existing, Value::list($template['fields'])),
        ];
    }
}
