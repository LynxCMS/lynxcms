<?php

declare(strict_types=1);

namespace LynxCms\Finder\Admin;

use LynxCms\Admin\Fields\ShortValue;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;

/**
 * The finder's results as table rows: title and editor link, template, status, last change and the
 * chosen field columns as short text.
 *
 * @internal
 */
final readonly class ResultRows
{
    /** Statuses a row names, by flag; a page with none of them is published. */
    private const array STATUSES = [PageStatus::UNPUBLISHED => 'unpublished', PageStatus::HIDDEN => 'hidden', PageStatus::LOCKED => 'locked'];

    public function __construct(private DefaultPresenter $presenter = new DefaultPresenter) {}

    /**
     * @param  list<string>  $columns  the fields shown as columns
     * @return list<array{id: int, title: string, url: string, template: string, statuses: list<array{key: string, label: string}>, changed: string|null, changedAt: string|null, cells: array<string, string>}>
     */
    public function from(PageArray $pages, array $columns = []): array
    {
        $rows = [];

        foreach ($pages as $page) {
            $rows[] = $this->row($page, $columns);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $columns  the fields shown as columns
     * @return array{id: int, title: string, url: string, template: string, statuses: list<array{key: string, label: string}>, changed: string|null, changedAt: string|null, cells: array<string, string>}
     */
    public function row(Page $page, array $columns = []): array
    {
        $cells = [];

        foreach ($columns as $column) {
            $cells[$column] = ShortValue::of($page, $column);
        }

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
            'cells' => $cells,
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
