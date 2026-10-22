<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\ItemNormalizer;
use LynxCms\Core\Fields\Items\ItemSettingsRules;
use LynxCms\Core\Support\Value;

/**
 * A list of items with the same fields, ProcessWire style: ordered, each switchable on and off
 * (ADR-0044). Items reuse existing fields, so their types, settings and translations apply.
 */
final class RepeaterType extends ItemFieldType
{
    public static function name(): string
    {
        return 'repeater';
    }

    public static function description(): string
    {
        return 'A sortable list of items that each hold the same fields, e.g. credits with a partner and a role. Setting "fields" lists existing fields as {"name", "required", "label", "width"}. Items can be switched off. Shared (each translatable subfield holds its own languages).';
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'fields' => $schema->array()->description('Fields of each item, in order: {"name", "required", "label", "description", "notes", "width"}. Labels may be text, or language code => text.'),
        ] + ItemSettingsRules::schema($schema);
    }

    public function settingsRules(): array
    {
        return ItemSettingsRules::fields('fields') + ItemSettingsRules::limits();
    }

    public function subfields(array $settings, ?string $type): array
    {
        return $this->subfieldsOf(Value::list($settings['fields'] ?? []));
    }

    protected function itemType(mixed $type, int $index, array $settings, ItemNormalizer $normalizer): ?string
    {
        return null;
    }
}
