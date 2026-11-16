<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Actions\Search\RebuildSearchIndex;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Search\SearchIndexReport;
use LynxCms\Core\Support\Value;

/**
 * Settings > Search index (ADR-0120): each language's rows, the fields each template indexes, a
 * page's indexed words, and search.rebuild for every page or one template's.
 *
 * @internal
 */
final class SearchIndexScreen extends Component
{
    use Notifies;

    /** The page whose indexed words to show: a path or an id. */
    public string $page = '';

    /** The template to rebuild; empty rebuilds every page. */
    public string $template = '';

    public ?string $status = null;

    /** Runs on mount and on every Livewire update, so the checks also guard /livewire/update. */
    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('search.manage', Value::string(__('lynx-admin::search_index.denied')));
    }

    public function rebuild(): void
    {
        $template = $this->template === '' ? [] : ['template' => $this->template];
        $result = lynx()->run(RebuildSearchIndex::class, $template)->value;
        $pages = Value::int(Value::map($result)['pages'] ?? 0);

        $this->notify(trans_choice('lynx-admin::search_index.rebuilt', $pages, ['count' => $pages]));
    }

    public function render(SearchIndexReport $report): View
    {
        $page = $this->lookedUp();

        return view('lynx-admin::livewire.search-index-screen', [
            'languages' => $report->languages(),
            'templates' => $report->templates(),
            'found' => $page,
            'rows' => $page === null || $page->id === null ? [] : $report->page($page->id),
        ]);
    }

    private function lookedUp(): ?Page
    {
        $reference = trim($this->page);

        if ($reference === '') {
            return null;
        }

        $page = lynx()->pages->get(ctype_digit($reference) ? (int) $reference : $reference);

        return $page instanceof Page ? $page : null;
    }
}
