<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

use LynxCms\Core\Pages\Items\Item;
use LynxCms\Core\Pages\Items\ItemList;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Support\Value;
use LynxCms\Seo\Support\AbsoluteUrl;

/**
 * Builds the node a mapped template's page stands for, e.g. an Event, and the nodes of mapped
 * pages it points to (its venue), each once in the graph and linked by @id.
 *
 * @internal
 */
final readonly class EntityBuilder
{
    /** A venue's page may point at its city's page, but no further: no cycles, no sprawl. */
    private const int MAX_DEPTH = 2;

    public function __construct(
        private Mappings $mappings,
        private SourceReader $reader,
        private ValueConverter $converter,
    ) {}

    public static function idOf(Page $page, string $type): string
    {
        return AbsoluteUrl::of($page->localUrl(lynx()->languages->current())).'#'.lcfirst($type);
    }

    /** @return array<string, mixed> */
    public function build(Page $page, Mapping $mapping, JsonLdGraph $graph, int $depth = 0): array
    {
        $node = ['@type' => $mapping->type, '@id' => self::idOf($page, $mapping->type)];

        foreach ($mapping->sources as $property => $source) {
            $kind = SchemaTypes::properties($mapping->type)[$property] ?? null;

            if ($kind === null || SchemaTypes::listOf($mapping->type, $property) !== null) {
                continue;
            }

            $value = match ($kind) {
                SchemaTypes::LIST => $this->items($this->reader->read($page, $source), $mapping, $property),
                SchemaTypes::PAGE => $source === Mapping::ORGANIZATION
                    ? ($graph->has(JsonLd::organizationId()) ? ['@id' => JsonLd::organizationId()] : null)
                    : $this->reference($this->reader->read($page, $source), $property, $graph, $depth),
                default => $this->converter->convert($this->reader->read($page, $source), $kind, $property),
            };
            $node = self::set($node, $property, $value);
        }

        return $node;
    }

    /**
     * One node per enabled item, with the list property's sub-properties read from its subfields.
     *
     * @return list<array<string, mixed>>
     */
    private function items(mixed $list, Mapping $mapping, string $property): array
    {
        $nodes = [];
        $items = $list instanceof ItemList ? $list->enabled() : [];

        foreach ($items as $item) {
            $nodes[] = $this->item($item, $mapping, $property);
        }

        return $nodes;
    }

    /** @return array<string, mixed> */
    private function item(Item $item, Mapping $mapping, string $list): array
    {
        $node = ['@type' => SchemaTypes::OBJECTS[$list] ?? 'Thing'];

        foreach ($mapping->sources as $property => $source) {
            if (str_starts_with($property, $list.'.')) {
                $kind = SchemaTypes::properties($mapping->type)[$property] ?? SchemaTypes::TEXT;
                $node = self::set($node, substr($property, strlen($list) + 1), $this->converter->convert($this->reader->read($item, $source), $kind, $property));
            }
        }

        return $node;
    }

    /**
     * A mapped page is added to the graph once and linked by @id; any other page is named.
     *
     * @return array<string, mixed>|null
     */
    private function reference(mixed $value, string $property, JsonLdGraph $graph, int $depth): ?array
    {
        $page = $value instanceof PageArray ? $value->first() : $value;

        if (! $page instanceof Page) {
            return null;
        }

        $mapping = $depth < self::MAX_DEPTH ? $this->mappings->for($page->template()->name) : null;

        if ($mapping === null) {
            return ['@type' => SchemaTypes::OBJECTS[$property] ?? 'Thing', 'name' => $this->converter->plain($page), 'url' => AbsoluteUrl::of($page->localUrl(lynx()->languages->current()))];
        }

        $id = self::idOf($page, $mapping->type);

        if (! $graph->has($id)) {
            $graph->add($this->build($page, $mapping, $graph, $depth + 1));
        }

        return ['@id' => $id];
    }

    /**
     * Sets a dotted property, making the nodes on the way ("address.streetAddress" makes a
     * PostalAddress). A node already linked by @id is left as it is.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function set(array $node, string $property, mixed $value): array
    {
        if ($value === null) {
            return $node;
        }

        $dot = strpos($property, '.');
        $head = $dot === false ? $property : substr($property, 0, $dot);

        if ($dot === false) {
            $node[$head] = is_array($value) && is_array($node[$head] ?? null) && ! isset($value['@id']) ? [...Value::map($node[$head]), ...Value::map($value)] : $value;

            return $node;
        }

        $rest = substr($property, $dot + 1);
        $child = is_array($node[$head] ?? null) ? Value::map($node[$head]) : ['@type' => SchemaTypes::OBJECTS[$head] ?? 'Thing'];

        if (! isset($child['@id'])) {
            $node[$head] = self::set($child, $rest, $value);
        }

        return $node;
    }
}
