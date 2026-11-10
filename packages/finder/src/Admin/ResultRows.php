<?php

declare(strict_types=1);

namespace LynxCms\Finder\Admin;

use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;

/**
 * The finder's results as table rows: title and editor link, template, status and last change.
 *
 * @internal
 */
final readonly class ResultRows
{
    /** Statuses a row names, by flag; a page with none of them is published. */
    private const array STATUSES = [PageStatus::UNPUBLISHED => 'unpublished', PageStatus::HIDDEN => 'hidden', PageStatus::LOCKED => 'locked'];

    public function __construct(private DefaultPresenter $presenter = new DefaultPresenter) {}

    /**
     * @return list<array{id: int, title: string, url: string, template: string, statuses: list<array{key: string, label: string}>, changed: string|null, changedAt: string|null}>
     */
    public function from(PageArray $pages): array
    {
        $rows = [];

        foreach ($pages as $page) {
            $rows[] = $this->row($page);
        }

        return $rows;
    }

    /**
     * @return array{id: int, title: string, url: string, template: string, statuses: list<array{key: string, label: string}>, changed: string|null, changedAt: string|null}
     */
    private function row(Page $page): array
    {
        $changed = $page->updatedAt === null ? null : now()->parse($page->updatedAt);
        $template = $page->template();

        return [
            'id' => (int) $page->id,
            'title' => $this->presenter->title($page),
            'url' => route('lynx.admin.pages.edit', $page->id),
            'template' => Value::string($template->label) ?: Value::string($template->name),
            'statuses' => $this->statuses($page),
            'changed' => $changed?->isoFormat('ll'),
            'changedAt' => $changed?->toDayDateTimeString(),
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function statuses(Page $page): array
    {
        $statuses = [];

        foreach (self::STATUSES as $flag => $key) {
            if (($page->status & $flag) === $flag) {
                $statuses[] = ['key' => $key, 'label' => Value::string(__("lynx-finder::admin.statuses.{$key}"))];
            }
        }

        return $statuses === [] ? [['key' => 'published', 'label' => Value::string(__('lynx-finder::admin.statuses.published'))]] : $statuses;
    }
}
