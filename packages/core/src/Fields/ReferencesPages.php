<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Closure;
use Illuminate\Database\Query\Builder;

/**
 * A field type whose values point at pages (ADR-0042). Lynx uses it to eager load those pages,
 * to list backlinks, and to write references as page keys in content packages and read them back.
 *
 * @api
 */
interface ReferencesPages
{
    /**
     * The page ids in a stored value.
     *
     * @param  array<string, mixed>  $settings
     * @return list<int>
     */
    public function pageIds(mixed $value, array $settings): array;

    /**
     * The value with every reference replaced by $map's result (an id becomes a key on export, a
     * key becomes an id on import); references mapped to null are dropped.
     *
     * @param  Closure(int|string): (int|string|null)  $map
     * @param  array<string, mixed>  $settings
     */
    public function mapPageIds(mixed $value, Closure $map, array $settings): mixed;

    /**
     * Narrow a query on the field's table to the rows that reference $pageId.
     *
     * @param  array<string, mixed>  $settings
     */
    public function whereReferences(Builder $query, int $pageId, array $settings): void;
}
