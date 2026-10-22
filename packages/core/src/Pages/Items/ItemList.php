<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Items;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use Traversable;

/**
 * A repeater's or matrix's items as templates read them (ADR-0044): the items switched on, in
 * order; all() includes the ones switched off. Pages the items reference load in one query.
 *
 * @implements IteratorAggregate<int, Item>
 *
 * @api
 */
final readonly class ItemList implements Countable, IteratorAggregate
{
    /** @var list<Item> */
    private array $items;

    /**
     * @param  list<mixed>  $stored
     */
    public function __construct(array $stored, Field $field, ItemFieldType $type)
    {
        $settings = $field->config();
        app(PageLoader::class)->many($type->pageIds($stored, $settings));
        $items = [];

        foreach ($stored as $item) {
            $item = Value::array($item);
            $itemType = Value::nullableString($item['type'] ?? null);
            $subfields = [];

            foreach ($type->subfields($settings, $itemType) as $subfield) {
                $subfields[$subfield->name()] = $subfield;
            }

            $items[] = new Item([
                'key' => Value::string($item['key'] ?? ''),
                'type' => $itemType,
                'enabled' => (bool) ($item['enabled'] ?? true),
                'depth' => Value::int($item['depth'] ?? 0),
                'values' => Value::map($item['values'] ?? []),
            ], $subfields, $field->name);
        }

        $this->items = $items;
    }

    /** @return list<Item> every item, switched off ones too */
    public function all(): array
    {
        return $this->items;
    }

    /** @return list<Item> */
    public function enabled(): array
    {
        return array_values(array_filter($this->items, fn (Item $item): bool => $item->isEnabled()));
    }

    public function first(): ?Item
    {
        return $this->enabled()[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->enabled() === [];
    }

    public function count(): int
    {
        return count($this->enabled());
    }

    /** @return Traversable<int, Item> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->enabled());
    }
}
