<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Query\SelectorException;

/**
 * The search box in the admin's top bar: plain text searches the index (title and the fields
 * templates mark searchable, ADR-0054), and anything with an
 * operator (=, <, >) runs as a selector, as lynx()->pages->find() does in code. Hidden and
 * unpublished pages are included unless the selector says otherwise, the number of results is
 * capped, and only pages the user may view are found.
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

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    /** The selector a query runs: an index search for text, the query itself for a selector. */
    public static function selector(string $query): string
    {
        $query = trim($query);

        if (preg_match('/[=<>]/', $query) !== 1) {
            // Inside a double-quoted value commas and pipes are literal; only quotes could end it.
            return 'search*="'.trim(str_replace(['"', "\0"], '', $query)).'", include=all';
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
            'isSelector' => preg_match('/[=<>]/', $this->query) === 1,
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

        try {
            $pages = lynx()->pages->find(self::selector($this->query), maxLimit: self::MAX_LIMIT, defaultLimit: self::LIMIT, scope: app(AdminAccess::class)->viewScope());
        } catch (SelectorException $exception) {
            return [[], 0, $exception->getMessage()];
        }

        return [array_values($pages->map($this->row(...))), $pages->total(), null];
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
