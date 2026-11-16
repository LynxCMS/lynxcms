<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Engines;

use Illuminate\Database\Query\Builder;
use LynxCms\Core\Search\SearchWord;
use LynxCms\Core\Support\Tables;

/**
 * PostgreSQL: the generated "document" tsvector (simple configuration, GIN index) matched with a
 * prefix query, or a word's singular exactly (ADR-0119), e.g. quatro:* & (estacoes:* | estacao).
 *
 * @internal
 */
final class PostgresSearchEngine extends SearchEngine
{
    public function whereMatches(Builder $query, string $pageIdColumn, array $words, int $languageId): void
    {
        $tsquery = implode(' & ', array_map(fn (SearchWord $word): string => $word->reduced === null ? $word->prefix.':*' : '('.$word->prefix.':* | '.$word->reduced.')', $words));

        $query->whereIn($pageIdColumn, fn (Builder $rows) => $rows->select('page_id')->from(Tables::SEARCH)
            ->where('language_id', $languageId)
            ->whereRaw("document @@ to_tsquery('simple', ?)", [$tsquery]));
    }
}
