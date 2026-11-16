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
 * Settings > Search index (ADR-0120): an overview tab with each language's rows and the fields
 * each template indexes, and a tools tab with search.rebuild (every page, a site template or a
 * system template) and a page's indexed words.
 *
 * @internal
 */
final class SearchIndexScreen extends Component
{
    use Notifies;

    /** The page whose indexed words to show: a path or an id. */
    public string $page = '';

    /** The site template to rebuild; empty with no system template rebuilds every page. */
    public string $template = '';

    /** The system template to rebuild (ADR-0115), chosen apart from the site's own. */
    public string $systemTemplate = '';

    public ?string $status = null;

    /** Runs on mount and on every Livewire update, so the checks also guard /livewire/update. */
    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('search.manage', Value::string(__('lynx-admin::search_index.denied')));
    }

    /** Choosing a site template clears the system one, so one rebuild runs at a time. */
    public function updatedTemplate(): void
    {
        $this->systemTemplate = $this->template === '' ? $this->systemTemplate : '';
    }

    public function updatedSystemTemplate(): void
    {
        $this->template = $this->systemTemplate === '' ? $this->template : '';
    }

    public function rebuild(): void
    {
        $chosen = $this->template !== '' ? $this->template : $this->systemTemplate;
        $template = $chosen === '' ? [] : ['template' => $chosen];
        $result = lynx()->run(RebuildSearchIndex::class, $template)->value;
        $pages = Value::int(Value::map($result)['pages'] ?? 0);

        $this->notify(trans_choice('lynx-admin::search_index.rebuilt', $pages, ['count' => $pages]));
    }

    public function render(SearchIndexReport $report): View
    {
        $page = $this->lookedUp();

        $templates = $report->templates();

        return view('lynx-admin::livewire.search-index-screen', [
            'languages' => $report->languages(),
            'siteTemplates' => array_values(array_filter($templates, fn (array $template): bool => $template['owner'] === null)),
            'systemTemplates' => array_values(array_filter($templates, fn (array $template): bool => $template['owner'] !== null)),
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
