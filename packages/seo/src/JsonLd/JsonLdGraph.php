<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

use LynxCms\Core\Support\Value;

/**
 * The nodes of one page's JSON-LD @graph. Extensions registered with JsonLd::extend() get it to
 * add, replace or remove nodes.
 *
 * @api
 */
final class JsonLdGraph
{
    /** @var list<array<string, mixed>> */
    private array $nodes = [];

    /** @param array<string, mixed> $node */
    public function add(array $node): self
    {
        $this->nodes[] = $node;

        return $this;
    }

    public function has(string $id): bool
    {
        return $this->find($id) !== null;
    }

    /** @return array<string, mixed>|null the node with this @id */
    public function find(string $id): ?array
    {
        foreach ($this->nodes as $node) {
            if (($node['@id'] ?? null) === $id) {
                return $node;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $node replaces the node with the same @id, or is added */
    public function replace(array $node): self
    {
        $this->remove(Value::string($node['@id'] ?? ''));

        return $this->add($node);
    }

    public function remove(string $id): self
    {
        $this->nodes = array_values(array_filter($this->nodes, fn (array $node): bool => ($node['@id'] ?? null) !== $id));

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /** @return array{'@context': string, '@graph': list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['@context' => 'https://schema.org', '@graph' => array_map(fn (array $node): array => Value::map(self::clean($node)), $this->nodes)];
    }

    /**
     * Without empty values, so absent fields leave no "name": "" behind.
     *
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private static function clean(array $node): array
    {
        $cleaned = [];

        foreach ($node as $key => $value) {
            $value = is_array($value) ? self::clean($value) : $value;
            $isTypeOnly = is_array($value) && array_keys($value) === ['@type'];

            if (! in_array($value, [null, '', []], true) && ! $isTypeOnly) {
                $cleaned[$key] = $value;
            }
        }

        return array_is_list($node) ? array_values($cleaned) : $cleaned;
    }
}
