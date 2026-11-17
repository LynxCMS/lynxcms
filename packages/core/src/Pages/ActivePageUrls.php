<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Generator;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * The URL every page has now, in each language, read straight from the paths table (ADR-0011):
 * pages whose template has no URL have no path, and the trash's pages are left out. For checks
 * that compare the site's URLs with something else, such as the app's routes.
 *
 * @internal
 */
final readonly class ActivePageUrls
{
    private const int CHUNK = 500;

    /**
     * URL => page id, a page once per language.
     *
     * @return Generator<string, int>
     */
    public function all(): Generator
    {
        $rows = DB::table(Tables::PAGE_PATHS.' as paths')
            ->join(Tables::LANGUAGES.' as languages', 'languages.id', '=', 'paths.language_id')
            ->where('paths.active', true)
            ->where('paths.path', '!=', Trash::NAME)
            ->where('paths.path', 'not like', Trash::NAME.'/%')
            ->orderBy('paths.id')
            ->select(['paths.id', 'paths.page_id', 'paths.path', 'languages.url_prefix'])
            ->lazyById(self::CHUNK, 'paths.id', 'id');

        foreach ($rows as $row) {
            yield PageUrl::of(Value::string($row->url_prefix), Value::string($row->path)) => Value::int($row->page_id);
        }
    }

    /**
     * Pages at $path or below it, as URL => page id: "/admin" and "/admin/team" are under "admin".
     *
     * @return array<string, int>
     */
    public function under(string $path): array
    {
        $base = '/'.trim($path, '/');
        $found = [];

        foreach ($this->all() as $url => $pageId) {
            $url = rtrim($url, '/') ?: '/';

            if (mb_strtolower($url) === $base || str_starts_with(mb_strtolower($url), $base.'/')) {
                $found[$url] = $pageId;
            }
        }

        return $found;
    }
}
