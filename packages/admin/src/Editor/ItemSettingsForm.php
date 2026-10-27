<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use Illuminate\Support\Str;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\EditorSettings;

/**
 * A repeater's "fields" and a matrix's "types" (ADR-0044, ADR-0045) as the field form's builder
 * edits them: every localized text with an input per language, and every type with a key that
 * stays the same while the form is open, for sorting. Unset overrides are left out on save, so
 * the stored settings stay as short as the ones written in PHP.
 *
 * @internal
 */
final class ItemSettingsForm
{
    private const array LOCALIZED = ['label', 'description', 'notes'];

    /**
     * @return array{name: string, label: array<string, string>, description: array<string, string>, notes: array<string, string>, required: bool, width: string}
     */
    public static function subfieldToForm(mixed $entry): array
    {
        $entry = Value::map($entry);

        return [
            'name' => Value::string($entry['name'] ?? ''),
            'label' => LocalizedForm::toForm($entry['label'] ?? null),
            'description' => LocalizedForm::toForm($entry['description'] ?? null),
            'notes' => LocalizedForm::toForm($entry['notes'] ?? null),
            'required' => ($entry['required'] ?? false) === true,
            'width' => (string) Value::int($entry['width'] ?? EditorSettings::FULL_WIDTH),
        ];
    }

    /**
     * @return list<array{name: string, label: array<string, string>, description: array<string, string>, notes: array<string, string>, required: bool, width: string}>
     */
    public static function subfieldsToForm(mixed $entries): array
    {
        return array_map(self::subfieldToForm(...), Value::list($entries));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function subfieldsFromForm(mixed $rows): array
    {
        return array_map(self::subfieldFromForm(...), Value::list($rows));
    }

    /**
     * @return list<array{key: string, slug: string, label: array<string, string>, item_label: string, fields: list<array<string, mixed>>}>
     */
    public static function typesToForm(mixed $types): array
    {
        return array_map(fn (mixed $type): array => self::typeToForm(Value::map($type)), Value::list($types));
    }

    /**
     * @return array{key: string, slug: string, label: array<string, string>, item_label: string, fields: list<array<string, mixed>>}
     */
    public static function blankType(): array
    {
        return self::typeToForm([]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function typesFromForm(mixed $rows): array
    {
        return array_map(fn (mixed $row): array => self::typeFromForm(Value::map($row)), Value::list($rows));
    }

    /**
     * @param  array<array-key, mixed>  $type
     * @return array{key: string, slug: string, label: array<string, string>, item_label: string, fields: list<array<string, mixed>>}
     */
    private static function typeToForm(array $type): array
    {
        return [
            'key' => Str::random(8),
            'slug' => Value::string($type['slug'] ?? ''),
            'label' => LocalizedForm::toForm($type['label'] ?? null),
            'item_label' => Value::string($type['item_label'] ?? ''),
            'fields' => self::subfieldsToForm($type['fields'] ?? []),
        ];
    }

    /**
     * A type without a slug takes one from its label, as the builder suggests while typing.
     *
     * @param  array<array-key, mixed>  $row
     * @return array<string, mixed>
     */
    private static function typeFromForm(array $row): array
    {
        $label = LocalizedForm::fromForm(Value::array($row['label'] ?? []));
        $slug = trim(Value::string($row['slug'] ?? ''));
        $itemLabel = trim(Value::string($row['item_label'] ?? ''));

        return array_filter([
            'slug' => $slug !== '' ? $slug : self::slugFrom($row),
            'label' => $label,
            'item_label' => $itemLabel !== '' ? $itemLabel : null,
            'fields' => self::subfieldsFromForm($row['fields'] ?? []),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    private static function slugFrom(array $row): string
    {
        $default = (string) lynx()->languages->default()->code;
        $labels = Value::map($row['label'] ?? []);

        return Str::snake(Str::ascii(Value::string($labels[$default] ?? '')));
    }

    /**
     * @return array<string, mixed>
     */
    private static function subfieldFromForm(mixed $row): array
    {
        $row = Value::map($row);
        $entry = ['name' => Value::string($row['name'] ?? '')];

        foreach (self::LOCALIZED as $key) {
            $text = LocalizedForm::fromForm(Value::array($row[$key] ?? []));

            if ($text !== null) {
                $entry[$key] = $text;
            }
        }

        if ((bool) ($row['required'] ?? false)) {
            $entry['required'] = true;
        }

        $width = Value::int($row['width'] ?? EditorSettings::FULL_WIDTH);

        if ($width !== EditorSettings::FULL_WIDTH) {
            $entry['width'] = $width;
        }

        return $entry;
    }
}
