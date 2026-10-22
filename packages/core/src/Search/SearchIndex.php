<?php

declare(strict_types=1);

namespace LynxCms\Core\Search;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Query\Clause;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Search\Engines\SearchEngine;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * The search index (ADR-0054): keeps each page's rows, one per language, and narrows selector
 * queries to the pages whose rows hold the words searched. Derived data, rebuilt at will.
 *
 * @internal
 */
final readonly class SearchIndex
{
    private const int CHUNK = 200;

    public function __construct(
        private SearchEngine $engine,
        private SearchText $text,
        private PageLoader $loader,
        private LanguageRegistry $languages,
    ) {}

    /** Index a page again, or drop its rows when it is gone or a draft. */
    public function update(int $pageId): void
    {
        $this->index([$pageId]);
    }

    public function remove(int $pageId): void
    {
        $this->engine->removePage($pageId);
    }

    public function removeLanguage(int $languageId): void
    {
        $this->engine->removeLanguage($languageId);
    }

    /**
     * Index every page again, or only those on a template. Returns how many pages were indexed.
     */
    public function rebuild(?int $templateId = null): int
    {
        if ($templateId === null) {
            $this->engine->clear();
        }

        $count = 0;
        $pages = DB::table(Tables::PAGES)->whereNull('draft_of')->when($templateId !== null, fn (Builder $q) => $q->where('template_id', $templateId));

        $pages->select('id')->chunkById(self::CHUNK, function ($rows) use (&$count): void {
            $ids = Value::ints($rows->pluck('id')->all());
            $this->index($ids);
            $count += count($ids);
        });

        return $count;
    }

    /**
     * A selector's search clause (search*="words"): every word, in the language read; values
     * separated by | are alternatives.
     */
    public function apply(Builder $query, Clause $clause, int $languageId): void
    {
        if (! in_array($clause->operator, ['*=', '%=', '~='], true)) {
            throw new SelectorException('Search the index with search*="words".');
        }

        $query->where(function (Builder $any) use ($clause, $languageId): void {
            foreach ($clause->values as $value) {
                $any->orWhere(fn (Builder $one) => $this->whereMatches($one, Tables::PAGES.'.id', $value, $languageId));
            }
        });
    }

    /**
     * Narrow a pages query to those whose text in the language holds every word of $search.
     */
    public function whereMatches(Builder $query, string $pageIdColumn, string $search, int $languageId): void
    {
        $words = SearchText::words($search);

        $words === [] ? $query->whereRaw('1 = 0') : $this->engine->whereMatches($query, $pageIdColumn, $words, $languageId);
    }

    /**
     * Index pages in one batch. Pages are forgotten by the loader afterwards, so a rebuild does not
     * keep the whole site in memory.
     *
     * @param  list<int>  $ids
     */
    private function index(array $ids): void
    {
        $live = Value::ints(DB::table(Tables::PAGES)->whereIn('id', $ids)->whereNull('draft_of')->pluck('id')->all());
        $pages = $this->loader->load($live);

        foreach ($ids as $id) {
            $page = $pages[$id] ?? null;
            $page instanceof Page ? $this->engine->replace($id, $this->contents($page)) : $this->engine->removePage($id);
            $this->loader->forget($id);
        }
    }

    /**
     * @return array<int, string> language id => folded words
     */
    private function contents(Page $page): array
    {
        $contents = [];

        foreach ($this->languages->all() as $language) {
            /** @var Language $language */
            $contents[$language->id] = $this->text->of($page, $language);
        }

        return $contents;
    }
}
