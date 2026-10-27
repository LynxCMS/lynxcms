<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Fields\Items\FilledList;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\ItemNormalizer;
use LynxCms\Core\Fields\Items\ItemSettingsRules;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * Typed blocks, like ProcessWire's RepeaterMatrix (ADR-0045): every item has a type, chosen from
 * the field's "types", and shows that type's fields. Types are stable slugs, so reordering or
 * importing them never changes what an item is. An item changing type keeps the other type's
 * values, hidden (rule 6).
 */
final class MatrixType extends ItemFieldType
{
    public static function name(): string
    {
        return 'matrix';
    }

    public static function description(): string
    {
        return 'Typed content blocks, e.g. text, image, video and quote. Setting "types" lists {"slug", "label", "fields"}; each item has a "type" (a slug) and holds that type\'s fields. Sortable; items can be switched off.';
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'types' => $schema->array()->description('Block types: {"slug": "quote", "label": text or language code => text, "fields": [{"name", "required", "label", "width"}], "item_label"}. Slugs never change.'),
        ] + ItemSettingsRules::schema($schema);
    }

    public function settingsRules(): array
    {
        return [
            'types' => [new FilledList('Add at least one block type.')],
            'types.*.slug' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,63}$/', 'distinct'],
            'types.*.label' => ['sometimes', 'nullable', ...LocalizedText::rules()],
            'types.*.item_label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ] + ItemSettingsRules::fields('types.*.fields') + ItemSettingsRules::limits();
    }

    public function subfields(array $settings, ?string $type): array
    {
        $definition = $this->type($settings, $type);

        return $definition === null ? [] : $this->subfieldsOf(Value::list($definition['fields'] ?? []));
    }

    public function allSubfields(array $settings): array
    {
        $all = [];

        foreach ($this->types($settings) as $definition) {
            foreach ($this->subfieldsOf(Value::list($definition['fields'] ?? [])) as $subfield) {
                $all[$subfield->name()] ??= $subfield;
            }
        }

        return array_values($all);
    }

    /**
     * A type's label in the current language, falling back to its slug.
     *
     * @param  array<string, mixed>  $settings
     */
    public function typeLabel(array $settings, string $type): string
    {
        $label = LocalizedText::from($this->type($settings, $type)['label'] ?? null);

        return $label->isEmpty() ? $type : $label->in();
    }

    /**
     * Slugs of the field's types, in order.
     *
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    public function slugs(array $settings): array
    {
        return array_map(fn (array $definition): string => Value::string($definition['slug'] ?? ''), $this->types($settings));
    }

    protected function itemType(mixed $type, int $index, array $settings, ItemNormalizer $normalizer): ?string
    {
        $slug = Value::string($type);

        if ($slug === '' || $this->type($settings, $slug) === null) {
            $normalizer->error("{$index}.type", $slug === ''
                ? 'Every block needs a type: '.implode(', ', $this->slugs($settings)).'.'
                : "There is no block type [{$slug}]. Use one of: ".implode(', ', $this->slugs($settings)).'.');
        }

        return $slug === '' ? null : $slug;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>|null
     */
    private function type(array $settings, ?string $slug): ?array
    {
        foreach ($this->types($settings) as $definition) {
            if (($definition['slug'] ?? null) === $slug) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function types(array $settings): array
    {
        return array_map(Value::map(...), Value::list($settings['types'] ?? []));
    }
}
