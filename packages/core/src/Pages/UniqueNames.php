<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * The unique status (ADR-0028): a unique page's name in the default language is used by no other
 * page anywhere in the tree, and no other page may take it. Other languages are not checked.
 *
 * @internal
 */
final readonly class UniqueNames
{
    public function __construct(private LanguageRegistry $languages, private PathResolver $paths) {}

    /**
     * Before a page is given $name in the default language or the unique flag.
     *
     * @param  string  $key  the input key the error belongs to
     */
    public function assertAllowed(?int $pageId, string $name, bool $isUnique, string $key): void
    {
        $others = $name === '' ? [] : $this->otherPagesNamed($name, $pageId);
        $owner = array_search(true, array_map(fn (int $status): bool => ($status & PageStatus::UNIQUE) !== 0, $others), true);

        if ($owner !== false) {
            $this->fail($key, "The name [{$name}] belongs to the unique page {$this->url($owner)}; choose another name.");
        }

        if ($isUnique && $others !== []) {
            $this->fail($key, "A unique page's name must not be used anywhere else, but [{$name}] is already used by {$this->url(array_key_first($others))}.");
        }
    }

    /** The unique page with $name in the default language, whatever its status (the URL shortcut). */
    public function pageNamed(string $name): ?int
    {
        $pages = $name === '' ? [] : $this->otherPagesNamed($name, null);

        return array_search(true, array_map(fn (int $status): bool => ($status & PageStatus::UNIQUE) !== 0, $pages), true) ?: null;
    }

    /**
     * Names are stored lowercase (PageName), so an exact match finds every spelling.
     *
     * @return array<int, int> page id => status of the other pages with $name in the default language
     */
    private function otherPagesNamed(string $name, ?int $pageId): array
    {
        $names = Tables::PAGE_LANGUAGES;
        $rows = DB::table($names)
            ->join(Tables::PAGES, Tables::PAGES.'.id', '=', "{$names}.page_id")
            ->where("{$names}.language_id", $this->languages->default()->id)
            ->where("{$names}.name", mb_strtolower($name))
            ->when($pageId !== null, fn (Builder $query): Builder => $query->where("{$names}.page_id", '!=', $pageId))
            ->orderBy("{$names}.page_id")
            ->pluck(Tables::PAGES.'.status', "{$names}.page_id");

        $others = [];
        foreach ($rows as $id => $status) {
            $others[Value::int($id)] = Value::int($status);
        }

        return $others;
    }

    private function url(int $pageId): string
    {
        $path = $this->paths->pathFor($pageId, $this->languages->default()) ?? '';

        return '/'.($path === '' ? '' : $path.'/');
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
