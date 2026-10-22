<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use Closure;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Value;
use stdClass;

/**
 * Items to rows and back (ADR-0044). An item is {key, type, enabled, depth, values}; values of
 * translatable subfields are maps of language code => value, split into one row per language so
 * language codes never appear in the database.
 *
 * @phpstan-type ItemValue array{key: string, type: string|null, enabled: bool, depth: int, values: array<string, mixed>}
 *
 * @internal
 */
final readonly class ItemRows
{
    public function __construct(private LanguageRegistry $languages, private FieldRegistry $fields) {}

    /**
     * @param  list<ItemValue>  $items
     * @param  (Closure(string): bool)|null  $holdsLanguages  whether a value key holds languages; by default, translatable fields
     * @return list<array<string, mixed>>
     */
    public function sleep(array $items, ?Closure $holdsLanguages = null): array
    {
        $holdsLanguages ??= fn (string $name): bool => $this->fields->find($name)?->isTranslatable() === true;

        $rows = [];

        foreach ($items as $sort => $item) {
            $shared = [];
            $translated = [];

            foreach ($item['values'] as $name => $value) {
                if (is_array($value) && $value !== [] && ! array_is_list($value) && $holdsLanguages($name)) {
                    foreach (Value::map($value) as $code => $languageValue) {
                        $translated[$this->languages->get($code)->id][$name] = $languageValue;
                    }
                } else {
                    $shared[$name] = $value;
                }
            }

            $rows[] = ['item' => $item['key'], 'language_id' => ItemTable::ITEM_ROW, 'sort' => $sort, 'enabled' => $item['enabled'],
                'depth' => $item['depth'], 'type' => $item['type'], 'data' => $this->json($shared)];

            foreach ($translated as $languageId => $values) {
                $rows[] = ['item' => $item['key'], 'language_id' => $languageId, 'sort' => null, 'enabled' => null, 'depth' => null, 'type' => null, 'data' => $this->json($values)];
            }
        }

        return $rows;
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<ItemValue>
     */
    public function wake(array $rows): array
    {
        $items = [];
        $translations = [];

        foreach ($rows as $row) {
            $key = Value::string($row->item);
            $data = Value::map(json_decode(Value::string($row->data, '{}'), true) ?? []);

            if (Value::int($row->language_id) === ItemTable::ITEM_ROW) {
                $items[$key] = ['key' => $key, 'type' => Value::nullableString($row->type), 'enabled' => (bool) $row->enabled,
                    'depth' => Value::int($row->depth), 'values' => $data, 'sort' => Value::int($row->sort)];
            } else {
                $translations[$key][(string) $this->languages->find(Value::int($row->language_id))?->code] = $data;
            }
        }

        foreach ($translations as $key => $byCode) {
            foreach ($byCode as $code => $values) {
                foreach ($values as $name => $value) {
                    if (isset($items[$key]) && $code !== '') {
                        $map = Value::map($items[$key]['values'][$name] ?? []);
                        $map[$code] = $value;
                        $items[$key]['values'][$name] = $map;
                    }
                }
            }
        }

        usort($items, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_map(fn (array $item): array => [
            'key' => $item['key'], 'type' => $item['type'], 'enabled' => $item['enabled'], 'depth' => $item['depth'], 'values' => $item['values'],
        ], $items);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function json(array $values): string
    {
        return json_encode((object) $values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
