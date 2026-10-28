<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\ActivityEntry;
use LynxCms\Admin\Editor\ActorNames;
use LynxCms\Admin\Editor\BacklinkFilter;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

/**
 * The read only part of the Settings tab: the page's old URLs and whether they still redirect,
 * the pages that link to it, and its activity. Reloads whenever the settings change the page.
 *
 * @api
 */
final class PageHistory extends Component
{
    public const int ACTIVITY_PER_PAGE = 20;

    #[Locked]
    public int $pageId;

    #[Locked]
    public int $activityLimit = self::ACTIVITY_PER_PAGE;

    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function showMoreActivity(): void
    {
        $this->activityLimit += self::ACTIVITY_PER_PAGE;
    }

    /** Deleting goes through redirects.delete; a refusal (locked page, unknown path) shows as an error. */
    public function deleteRedirect(string $language, string $path): void
    {
        lynx()->pages->deleteRedirect($this->pageId, $path, $language);
    }

    /** The settings renamed, moved or changed the page: rendering again picks it up. */
    #[On('lynx-page-changed')]
    public function refresh(): void {}

    public function render(): View
    {
        // One more than shown tells whether "Show more" has anything to add.
        $activity = lynx()->pages->activity($this->pageId, $this->activityLimit + 1);
        $names = new ActorNames;

        return view('lynx-admin::livewire.page-history', [
            'redirects' => lynx()->pages->redirects($this->pageId),
            // A page without a URL has no old URLs to send on.
            'hasUrl' => lynx()->pages->get($this->pageId)?->template()->hasUrl() ?? true,
            'backlinks' => lynx()->pages->backlinks($this->pageId, selector: app(BacklinkFilter::class)->selector())->map($this->backlink(...)),
            'isLocked' => lynx()->pages->get($this->pageId)?->hasStatus(PageStatus::LOCKED) ?? true,
            'activity' => array_map(fn (ActivityEvent $event): array => ActivityEntry::from($event, $names), array_slice($activity, 0, $this->activityLimit)),
            'hasMoreActivity' => count($activity) > $this->activityLimit,
        ]);
    }

    /**
     * @return array{id: int, title: string, context: string}
     */
    private function backlink(Page $page): array
    {
        $titles = new DefaultPresenter;
        $parent = $page->parent();
        $template = $page->template()->label ?: $page->template()->name;

        return [
            'id' => (int) $page->id,
            'title' => $titles->title($page),
            'context' => $template.($parent instanceof Page ? ' under '.$titles->title($parent) : ''),
        ];
    }
}
