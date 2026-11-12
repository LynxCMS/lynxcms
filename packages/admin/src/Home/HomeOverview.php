<?php

declare(strict_types=1);

namespace LynxCms\Admin\Home;

use Carbon\CarbonInterface;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Editor\ActivityEntry;
use LynxCms\Admin\Editor\ActorNames;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Admin\Tree\TreeFilters;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\ScheduleReader;
use LynxCms\Core\Query\PageScope;
use LynxCms\Core\Support\Value;

/**
 * What the admin's home screen lists: pages with a draft, the next scheduled changes and the
 * pages changed last. Each list holds only pages the current user may view, outside the trash,
 * system pages and module branches, as the page tree's filters do (TreeFilters).
 *
 * @internal
 */
final readonly class HomeOverview
{
    public const int LIMIT = 6;

    /** Scheduled pages read at most: schedules are few, and the list shows the soonest. */
    private const int SCHEDULED_PAGES = 500;

    /** Activity events read to find the pages changed last; several events often share a page. */
    private const int RECENT_EVENTS = 100;

    private TreeFilters $filters;

    private ?PageScope $scope;

    public function __construct(AdminAccess $access, private ScheduleReader $schedules, private ActorNames $names = new ActorNames)
    {
        $this->scope = $access->viewScope();
        $this->filters = new TreeFilters($this->scope);
    }

    /**
     * @return array{total: int, pages: list<array{id: int, title: string, url: string, date: string|null, changedAt: string|null}>}
     */
    public function drafts(): array
    {
        $found = lynx()->pages->find($this->filters->selector(TreeFilters::DRAFTS).', sort=-modified, limit='.self::LIMIT, scope: $this->scope);
        $pages = [];

        foreach ($found as $page) {
            $changedAt = $page->updatedAt === null ? null : now()->parse($page->updatedAt);
            $pages[] = [...$this->link($page), 'date' => $changedAt === null ? null : $this->shortDate($changedAt), 'changedAt' => $changedAt?->toDayDateTimeString()];
        }

        return ['total' => $found->total(), 'pages' => $pages];
    }

    /**
     * The soonest scheduled changes, and how many pages have one. The same change to a page at the
     * same time in several languages is one entry, naming them all.
     *
     * @return array{total: int, entries: list<array{id: int, title: string, url: string, action: string, languages: list<string>, date: string, when: string, at: string, iso: string, failed: bool}>}
     */
    public function upcoming(): array
    {
        $ids = lynx()->pages->find($this->filters->selector(TreeFilters::SCHEDULED).', limit='.self::SCHEDULED_PAGES, scope: $this->scope)->ids();
        $labels = $this->languageLabels();
        $entries = $this->schedules->upcoming($ids, self::LIMIT * max(1, count($labels)));
        $pages = $this->pages(array_column($entries, 'page'));
        $upcoming = [];

        foreach ($entries as $entry) {
            $key = $entry['page'].'|'.$entry['action'].'|'.$entry['at']->getTimestamp();

            if (isset($upcoming[$key])) {
                $upcoming[$key]['languages'] = array_values(array_filter([...$upcoming[$key]['languages'], $labels[$entry['language']] ?? '']));
                $upcoming[$key]['failed'] = $upcoming[$key]['failed'] || $entry['error'] !== null;
            } elseif (isset($pages[$entry['page']]) && count($upcoming) < self::LIMIT) {
                $upcoming[$key] = [...$this->link($pages[$entry['page']]), 'action' => $entry['action'], 'languages' => array_values(array_filter([$labels[$entry['language']] ?? ''])), 'date' => $this->shortDate($entry['at'], withTime: true), 'when' => $entry['at']->diffForHumans(), 'at' => $entry['at']->toDayDateTimeString(), 'iso' => $entry['at']->toIso8601String(), 'failed' => $entry['error'] !== null];
            }
        }

        return ['total' => count($ids), 'entries' => array_values($upcoming)];
    }

    /**
     * The pages changed last, each with its latest change.
     *
     * @return list<array{id: int, title: string, url: string, when: string, at: string, who: string, what: array<string, mixed>}>
     */
    public function recent(): array
    {
        $latest = [];

        foreach (ActivityEvent::query()->where('subject_type', 'page')->orderByDesc('id')->limit(self::RECENT_EVENTS)->get() as $event) {
            $latest[(int) $event->subject_id] ??= $event;
        }

        $pages = $this->pages(array_keys($latest));
        $recent = [];

        foreach ($latest as $id => $event) {
            if (isset($pages[$id]) && count($recent) < self::LIMIT) {
                $recent[] = [...$this->link($pages[$id]), ...ActivityEntry::from($event, $this->names)];
            }
        }

        return $recent;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, Page> the pages among them the lists may show, by id
     */
    private function pages(array $ids): array
    {
        $pages = [];

        foreach (lynx()->pages->find($this->filters->among($ids).', limit='.max(1, count($ids)), scope: $this->scope) as $page) {
            $pages[(int) $page->id] = $page;
        }

        return $pages;
    }

    /**
     * What each language is called, by code; empty on a site with one language, where a schedule
     * always covers the whole page.
     *
     * @return array<string, string>
     */
    private function languageLabels(): array
    {
        if (! lynx()->languages->isMultilingual()) {
            return [];
        }

        return lynx()->languages->all()->mapWithKeys(fn (Language $language): array => [Value::string($language->code) => $language->tabLabel()])->all();
    }

    /** "4 Nov", with the year when it is not this year's, and the time when asked. */
    private function shortDate(CarbonInterface $at, bool $withTime = false): string
    {
        $format = ($at->isCurrentYear() ? 'D MMM' : 'D MMM YYYY').($withTime ? ', HH:mm' : '');

        return $at->isoFormat($format);
    }

    /** @return array{id: int, title: string, url: string} */
    private function link(Page $page): array
    {
        return ['id' => (int) $page->id, 'title' => (new DefaultPresenter)->title($page), 'url' => route('lynx.admin.pages.edit', $page->id)];
    }
}
