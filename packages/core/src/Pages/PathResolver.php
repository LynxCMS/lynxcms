<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Read side of page paths (ADR-0011): the active path of a page per language, and which page a
 * path belongs to. Old paths stay as inactive rows and resolve to a redirect.
 *
 * @api
 */
final class PathResolver implements Flushable
{
    /** Ids per query, well below the bind limits of SQLite and PostgreSQL. */
    private const int CHUNK = 500;

    /** @var array<string, string|null> */
    private array $paths = [];

    /** @var array<int, true> pages loaded together, whose paths are fetched in one query on first use */
    private array $queued = [];

    /**
     * Remember pages that were loaded together, so the first path lookup for any of them loads
     * the paths of all of them in that language (one query instead of one per page).
     *
     * @param  list<int>  $pageIds
     */
    public function queue(array $pageIds): void
    {
        $this->queued += array_fill_keys($pageIds, true);
    }

    public function pathFor(int $pageId, Language $language): ?string
    {
        $key = $pageId.':'.$language->id;

        if (! array_key_exists($key, $this->paths)) {
            $this->load([...$this->unresolved($language), $pageId], $language);
        }

        return $this->paths[$key] ?? null;
    }

    /**
     * Find the page for a path in a language: [page id, whether the path is the active one].
     *
     * @return array{0: int, 1: bool}|null
     */
    public function resolve(Language $language, string $path): ?array
    {
        $path = trim($path, '/');

        $row = DB::table(Tables::PAGE_PATHS)
            ->where('language_id', $language->id)
            ->whereRaw('lower(path) = ?', [mb_strtolower($path)])
            ->orderByDesc('active')
            ->orderByDesc('id')
            ->first();

        return $row === null ? null : [Value::int($row->page_id), (bool) $row->active];
    }

    /**
     * Old paths of a page, newest first, as [language id, path]. Paths the page uses again in
     * that language are left out, and each path is listed once.
     *
     * @return list<array{0: int, 1: string}>
     */
    public function history(int $pageId): array
    {
        $rows = DB::table(Tables::PAGE_PATHS)->where('page_id', $pageId)->orderByDesc('active')->orderByDesc('id')->get(['language_id', 'path', 'active']);
        $seen = [];
        $history = [];

        foreach ($rows as $row) {
            $key = Value::int($row->language_id).':'.mb_strtolower(Value::string($row->path));

            if (! isset($seen[$key]) && ! (bool) $row->active) {
                $history[] = [Value::int($row->language_id), Value::string($row->path)];
            }

            $seen[$key] = true;
        }

        return $history;
    }

    public function flush(): void
    {
        $this->paths = [];
        $this->queued = [];
    }

    /**
     * Queued pages without a cached path in this language.
     *
     * @return list<int>
     */
    private function unresolved(Language $language): array
    {
        return array_values(array_filter(array_keys($this->queued), fn (int $id): bool => ! array_key_exists($id.':'.$language->id, $this->paths)));
    }

    /**
     * @param  list<int>  $pageIds
     */
    private function load(array $pageIds, Language $language): void
    {
        foreach (array_chunk(array_values(array_unique($pageIds)), self::CHUNK) as $chunk) {
            $found = DB::table(Tables::PAGE_PATHS)
                ->whereIn('page_id', $chunk)
                ->where('language_id', $language->id)
                ->where('active', true)
                ->pluck('path', 'page_id')
                ->all();

            foreach ($chunk as $id) {
                $this->paths[$id.':'.$language->id] = isset($found[$id]) ? Value::string($found[$id]) : null;
            }
        }
    }
}
