<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Engines;

use Illuminate\Database\Query\Builder;
use LynxCms\Core\Support\Tables;

/**
 * Databases without a full text engine Lynx knows: every word must begin a word of the row.
 * Rows are words joined by single spaces, so " word" marks a word beginning.
 *
 * @internal
 */
final class LikeSearchEngine extends SearchEngine
{
    public function whereMatches(Builder $query, string $pageIdColumn, array $words, int $languageId): void
    {
        $query->whereIn($pageIdColumn, function (Builder $rows) use ($words, $languageId): void {
            $rows->select('page_id')->from(Tables::SEARCH)->where('language_id', $languageId);

            foreach ($words as $word) {
                $rows->whereRaw("(' ' || content) LIKE ?", ['% '.$word.'%']);
            }
        });
    }
}
