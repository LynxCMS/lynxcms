<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Seo\SeoSchema;

/**
 * The template mappings editors keep on the SEO settings page, one matrix item each.
 *
 * @internal
 */
final class Mappings
{
    /**
     * Every item of the settings page's mapping field, enabled or not, by its index.
     *
     * @return array<int, array{enabled: bool, template: string, type: string, lines: string}>
     */
    public static function items(?Page $settings): array
    {
        if ($settings?->templateField(SeoSchema::SCHEMAS) === null) {
            return [];
        }

        $items = [];

        foreach (Value::list($settings->getUnformatted(SeoSchema::SCHEMAS)) as $index => $item) {
            $item = Value::map($item);
            $values = Value::map($item['values'] ?? []);
            $items[$index] = [
                'enabled' => (bool) ($item['enabled'] ?? true),
                'template' => trim(Value::string($values[SeoSchema::SCHEMA_TEMPLATE] ?? '')),
                'type' => trim(Value::string($values[SeoSchema::SCHEMA_TYPE] ?? '')),
                'lines' => Value::string($values[SeoSchema::SCHEMA_MAP] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * The settings page's matrix items for these mappings, as SavePage takes them.
     *
     * @param  list<array{enabled: bool, template: string, type: string, lines: string}>  $mappings
     * @return list<array<string, mixed>>
     */
    public static function toItems(array $mappings): array
    {
        return array_map(fn (array $mapping): array => ['type' => SeoSchema::SCHEMA_ITEM, 'enabled' => $mapping['enabled'], 'values' => [
            SeoSchema::SCHEMA_TEMPLATE => $mapping['template'], SeoSchema::SCHEMA_TYPE => $mapping['type'], SeoSchema::SCHEMA_MAP => $mapping['lines'],
        ]], array_values($mappings));
    }

    /** The enabled mapping for a template, if any. */
    public function for(string $template): ?Mapping
    {
        foreach (self::items(lynx()->settings(SeoSchema::SETTINGS_TEMPLATE)) as $item) {
            if ($item['enabled'] && $item['template'] === $template && SchemaTypes::has($item['type'])) {
                return new Mapping($template, $item['type'], Mapping::parse($item['lines'])[0]);
            }
        }

        return null;
    }
}
