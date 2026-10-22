<?php

declare(strict_types=1);

namespace LynxCms\Core\Strings;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Edited strings, read once per page and request (ADR-0047).
 *
 * @internal
 */
final class StringStore
{
    /** @var array<int, array<string, array<int, string>>> page id => key => language id => text */
    private array $pages = [];

    /**
     * @return array<string, array<int, string>> key => language id => text
     */
    public function forPage(int $pageId): array
    {
        if (! isset($this->pages[$pageId])) {
            $this->pages[$pageId] = [];

            foreach (DB::table(Tables::STRINGS)->where('page_id', $pageId)->get() as $row) {
                $this->pages[$pageId][Value::string($row->key)][Value::int($row->language_id)] = Value::string($row->value);
            }
        }

        return $this->pages[$pageId];
    }

    public function forget(int $pageId): void
    {
        unset($this->pages[$pageId]);
    }
}
