<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use LynxCms\Contracts\Fields\FieldType;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\UseSettings;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * Settings shared by item fields (ADR-0044): the subfield entries and the list limits.
 *
 * @internal
 */
final class ItemSettingsRules
{
    /**
     * Rules for a list of subfield entries under $prefix ("fields", or "types.*.fields").
     *
     * @return array<string, mixed>
     */
    public static function fields(string $prefix): array
    {
        return [
            $prefix => [new FilledList('List at least one field.')],
            "{$prefix}.*.name" => ['required', 'string', self::subfieldExists()],
            "{$prefix}.*" => [self::settingsPerUse()],
            "{$prefix}.*.required" => ['sometimes', 'boolean'],
            "{$prefix}.*.width" => ['sometimes', 'integer', 'min:10', 'max:100'],
            "{$prefix}.*.label" => ['sometimes', 'nullable', ...LocalizedText::rules()],
            "{$prefix}.*.description" => ['sometimes', 'nullable', ...LocalizedText::rules(2000)],
            "{$prefix}.*.notes" => ['sometimes', 'nullable', ...LocalizedText::rules(500)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function limits(): array
    {
        return [
            'min' => ['sometimes', 'integer', 'min:0'],
            'max' => ['sometimes', 'integer', 'min:0'],
            'max_depth' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'item_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'add_label' => ['sometimes', 'nullable', ...LocalizedText::rules()],
            'collapse' => ['sometimes', 'in:open,collapsed,new_open'],
        ];
    }

    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'min' => $schema->integer()->description('Fewest items allowed (0 = none required).'),
            'max' => $schema->integer()->description('Most items allowed (0 = no limit).'),
            'max_depth' => $schema->integer()->description('Indent levels items may have, 0 to 5. Default 0.'),
            'item_label' => $schema->string()->description('Header of each item in the admin, e.g. "{partner} · {role}".'),
            'add_label' => $schema->string()->description('Text of the add button: text, or language code => text.'),
            'collapse' => $schema->string()->enum(['open', 'collapsed', 'new_open'])->description('Which items start open in the admin. Default new_open.'),
        ];
    }

    /**
     * Whether fields of a type may be listed in an item field's entries: fields that store items themselves
     * cannot, except the ones that nest their items in the item's JSON (media).
     */
    public static function canBeSubfield(FieldType $type): bool
    {
        return ! $type instanceof StoresItems || $type instanceof NestsInItems;
    }

    /** Settings an entry sets for its field must be ones the field's type takes per use (ADR-0094). */
    private static function settingsPerUse(): Closure
    {
        return function (string $attribute, mixed $entry, Closure $fail): void {
            $entry = Value::map($entry);
            $field = app(FieldRegistry::class)->find(Value::string($entry['name'] ?? ''));
            $problem = $field === null ? null : UseSettings::problem($field, $entry);

            if ($problem !== null) {
                $fail($problem);
            }
        };
    }

    /** The subfield must be an existing field that does not store items itself. */
    private static function subfieldExists(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $name = Value::string($value);
            $field = app(FieldRegistry::class)->find($name);

            if ($field === null) {
                $fail("Field [{$name}] does not exist. Create it first with lynx()->fields->add('{$name}', ...) or the fields.create action.");
            } elseif (! self::canBeSubfield($field->fieldType())) {
                $fail("Field [{$name}] stores items itself; items cannot nest. Use depth instead.");
            }
        };
    }
}
