<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Query\SelectorPolicy;

/**
 * The search box in the admin's top bar: plain text searches the index (title and the fields
 * templates mark searchable, ADR-0054), and anything with an
 * operator (=, <, >) runs as a selector, as lynx()->pages->find() does in code. Hidden and
 * unpublished pages are included unless the selector says otherwise, the number of results is
 * capped, and only pages the user may view are found. Selectors use only fields the user may
 * view; while the index holds one they may not, plain text searches titles (ADR-0153).
 *
 * @internal
 */
final class AdminSearch extends Component
{
    /** Results shown when the selector sets no limit. */
    public const int LIMIT = 10;

    /** The most a selector's limit= can ask for. */
    public const int MAX_LIMIT = 50;

    public string $query = '';

    /** Set by boot() on every request: the signed in user's rights. */
    private AdminAccess $access;

    public function boot(AdminAccess $access): void
    {
        $this->access = $access;
        $access->authorizeScreen();
    }

    /** The selector a query runs: an index search for text, the query itself for a selector. */
    public static function selector(string $query): string
    {
        $query = trim($query);

        if (! self::isSelector($query)) {
            return 'search*="'.self::plainText($query).'", include=all';
        }

        return preg_match('/(^|,)\s*include\s*=/', $query) === 1 ? $query : $query.', include=all';
    }

    public function render(): View
    {
        [$results, $total, $error] = $this->search();

        return view('lynx-admin::livewire.admin-search', [
            'results' => $results,
            'total' => $total,
            'error' => $error,
            'isSelector' => self::isSelector($this->query),
        ]);
    }

    /**
     * @return array{0: list<array{id: int, title: string, path: string, template: string, isUnpublished: bool}>, 1: int, 2: string|null}
     */
    private function search(): array
    {
        if (trim($this->query) === '') {
            return [[], 0, null];
        }

        // Only through fields the user may view (ADR-0153): a filter on a hidden one would tell its value.
        $policy = SelectorPolicy::forActor($this->access->actor());

        try {
            $pages = $this->find(self::selector($this->query), $policy);
        } catch (SelectorException $exception) {
            if (self::isSelector($this->query)) {
                return [[], 0, $exception->getMessage()];
            }

            // The search index holds a field the user may not view: plain text searches titles only.
            $pages = $this->find('title%="'.self::plainText($this->query).'", include=all', $policy);
        }

        return [array_values($pages->map($this->row(...))), $pages->total(), null];
    }

    private function find(string $selector, ?SelectorPolicy $policy): PageArray
    {
        return app(PageFinder::class)->find($selector, maxLimit: self::MAX_LIMIT, defaultLimit: self::LIMIT, scope: $this->access->viewScope(), policy: $policy);
    }

    private static function isSelector(string $query): bool
    {
        return preg_match('/[=<>]/', $query) === 1;
    }

    /** Text safe inside a double-quoted selector value, where commas and pipes are literal and only quotes could end it. */
    private static function plainText(string $query): string
    {
        return trim(str_replace(['"', "\0"], '', trim($query)));
    }

    /**
     * @return array{id: int, title: string, path: string, template: string, isUnpublished: bool}
     */
    private function row(Page $page): array
    {
        return [
            'id' => (int) $page->id,
            'title' => (new DefaultPresenter)->title($page),
            'path' => '/'.ltrim(rtrim($page->path() ?? '', '/').'/', '/'),
            'template' => (string) ($page->template()->label ?: $page->template()->name),
            'isUnpublished' => $page->hasStatus(PageStatus::UNPUBLISHED),
        ];
    }
}
