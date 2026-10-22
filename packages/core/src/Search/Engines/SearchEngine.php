<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Engines;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use LynxCms\Core\Support\Tables;

/**
 * Stores the search index's rows and finds pages in them (ADR-0054). Rows live in lynx_search on
 * every database; each engine adds its database's full text search over them.
 *
 * @internal
 */
abstract class SearchEngine
{
    public function __construct(protected readonly Connection $db) {}

    /**
     * Narrow $query to pages whose index row in the language holds every word, each as a word
     * beginning. Words are folded (SearchText::words) and hold only letters and digits.
     *
     * @param  non-empty-list<string>  $words
     */
    abstract public function whereMatches(Builder $query, string $pageIdColumn, array $words, int $languageId): void;

    /**
     * Replace a page's rows.
     *
     * @param  array<int, string>  $contentByLanguage  language id => folded words
     */
    public function replace(int $pageId, array $contentByLanguage): void
    {
        $this->delete('page_id', $pageId);
        $rows = [];

        foreach ($contentByLanguage as $languageId => $content) {
            $rows[] = ['page_id' => $pageId, 'language_id' => $languageId, 'content' => $content];
        }

        if ($rows !== []) {
            $this->db->table(Tables::SEARCH)->insert($rows);
            $this->inserted($pageId);
        }
    }

    public function removePage(int $pageId): void
    {
        $this->delete('page_id', $pageId);
    }

    public function removeLanguage(int $languageId): void
    {
        $this->delete('language_id', $languageId);
    }

    public function clear(): void
    {
        $this->delete(null, 0);
    }

    /** Rows of the page were just inserted: engines that keep their own table add them. */
    protected function inserted(int $pageId): void {}

    /** Rows matching are about to be deleted: engines that keep their own table drop them. */
    protected function deleting(Builder $rows): void {}

    /** Delete the rows whose $column is $value, or every row when $column is null. */
    private function delete(?string $column, int $value): void
    {
        $rows = $this->db->table(Tables::SEARCH);

        if ($column !== null) {
            $rows->where($column, $value);
        }

        $this->deleting(clone $rows);
        $rows->delete();
    }
}
