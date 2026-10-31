<?php

declare(strict_types=1);

namespace LynxCms\Cache\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Cache\Actions\ClearPageCache;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Cache\Pages\PageCacheSettings;
use LynxCms\Cache\Pages\TemplateRule;
use LynxCms\Cache\Pages\TemplateRules;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;

/**
 * The page editor's Cache tab: whether guests get a stored copy of the page, and why not when they
 * don't, for anyone who may view the page. People who manage the page cache also get the
 * template's rule, how to fix what keeps the page out, clearing, and how the page cache works.
 *
 * @internal
 */
final class PageCacheTab extends Component
{
    #[Locked]
    public int $pageId;

    /** What the last clear did. */
    public string $status = '';

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function mount(): void
    {
        abort_unless(app(AdminAccess::class)->allows('pages.view', $this->page()), 403);
    }

    public function clear(): void
    {
        abort_unless($this->canManage(), 403);
        lynx()->run(ClearPageCache::class, ['pages' => [$this->pageId]]);
        $this->status = Value::string(__('lynx-cache::admin.status.tab_cleared'));
    }

    public function render(TemplateRules $rules, PageCacheSettings $settings): View
    {
        $page = $this->page();
        $rule = $rules->for((string) $page->template()->name);
        $entries = $this->entries();

        return view('lynx-cache::admin.livewire.page-tab', [
            'page' => $page,
            'rule' => $rule,
            'entries' => $entries,
            'reason' => $this->reason($page, $rule),
            'hosts' => $settings->hosts(),
            'canManage' => $this->canManage(),
            'languages' => lynx()->languages->all()->mapWithKeys(fn ($language): array => [$language->id => $language->tabLabel()])->all(),
        ]);
    }

    private function page(): Page
    {
        $page = lynx()->pages->get($this->pageId);
        abort_unless($page instanceof Page, 404);

        return $page;
    }

    private function canManage(): bool
    {
        return app(AdminAccess::class)->allows('page_cache.manage');
    }

    /** Why guests never get a stored copy of the page, or null when they may. */
    private function reason(Page $page, ?TemplateRule $rule): ?string
    {
        return match (true) {
            config('lynx-cache.pages.enabled') !== true => 'off',
            $rule === null => 'template',
            $page->hasStatus(PageStatus::UNPUBLISHED) => 'unpublished',
            default => null,
        };
    }

    /** @return Collection<int, PageCacheEntry> */
    private function entries(): Collection
    {
        return PageCacheEntry::query()->where('page_id', $this->pageId)->orderBy('path')->orderBy('query')->get();
    }
}
