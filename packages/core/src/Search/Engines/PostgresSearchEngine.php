<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Engines;

use Illuminate\Database\Query\Builder;
use LynxCms\Core\Support\Tables;

/**
 * PostgreSQL: the generated "document" tsvector (simple configuration, GIN index) matched with a
 * prefix query, e.g. quatro:* & estac:*.
 *
 * @internal
 */
final class PostgresSearchEngine extends SearchEngine
{
    public function whereMatches(Builder $query, string $pageIdColumn, array $words, int $languageId): void
    {
        $tsquery = implode(' & ', array_map(fn (string $word): string => $word.':*', $words));

        $query->whereIn($pageIdColumn, fn (Builder $rows) => $rows->select('page_id')->from(Tables::SEARCH)
            ->where('language_id', $languageId)
            ->whereRaw("document @@ to_tsquery('simple', ?)", [$tsquery]));
    }
}
