<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Engines;

use Illuminate\Database\Query\Builder;
use LynxCms\Core\Query\Sql;
use LynxCms\Core\Support\Tables;

/**
 * SQLite: an external content FTS5 table over lynx_search, kept in step by hand (FTS5 needs the
 * old text to forget a row), matched with prefix terms, e.g. "quatro"* "estac"*.
 *
 * @internal
 */
final class SqliteSearchEngine extends SearchEngine
{
    public function whereMatches(Builder $query, string $pageIdColumn, array $words, int $languageId): void
    {
        $match = implode(' ', array_map(fn (string $word): string => '"'.$word.'"*', $words));
        $fts = $this->fts();

        $query->whereIn($pageIdColumn, fn (Builder $rows) => $rows->select('page_id')->from(Tables::SEARCH)
            ->where('language_id', $languageId)
            ->whereRaw(Sql::trusted('id IN (SELECT rowid FROM '.$fts.' WHERE '.$fts.' MATCH ?)'), [$match]));
    }

    protected function inserted(int $pageId): void
    {
        $this->db->statement(Sql::trusted('INSERT INTO '.$this->fts().' (rowid, content) SELECT id, content FROM '.$this->search().' WHERE page_id = ?'), [$pageId]);
    }

    protected function deleting(Builder $rows): void
    {
        $select = $rows->select(['id', 'content']);

        $this->db->statement(Sql::trusted('INSERT INTO '.$this->fts().' ('.$this->fts().', rowid, content) SELECT \'delete\', id, content FROM ('.$select->toSql().')'), $select->getBindings());
    }

    private function fts(): string
    {
        return $this->db->getQueryGrammar()->wrapTable(Tables::SEARCH_FTS);
    }

    private function search(): string
    {
        return $this->db->getQueryGrammar()->wrapTable(Tables::SEARCH);
    }
}
