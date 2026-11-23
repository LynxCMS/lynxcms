<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Admin\References\RebuildIndex;
use LynxCms\Admin\References\ReportRows;
use LynxCms\Core\Actions\Pages\RebuildPageReferences;
use LynxCms\Core\Support\Value;

/**
 * Settings > Page references (ADR-0145): one report at a time (broken links, pages nothing links to, the
 * most linked pages), 50 rows a page, limited to the pages the user may view. Users with
 * page_references.manage also see the index's health: a check (the rebuild's dry run) and a
 * rebuild that runs on the queue.
 *
 * @internal
 */
final class PageReferencesScreen extends Component
{
    use Notifies;

    public const array REPORTS = ['broken', 'unlinked', 'linked'];

    public const int PER_PAGE = 50;

    #[Url]
    public string $report = 'broken';

    #[Url(except: 0)]
    public int $start = 0;

    /** The last notice, for screen readers and tests (Notifies). */
    public ?string $status = null;

    /** @var array{pages: int, added: int, removed: int, resorted: int}|null the last check of the index */
    #[Locked]
    public ?array $health = null;

    /** Set by boot() on every request: the signed in user's rights. */
    private AdminAccess $access;

    /** Every request, updates included: they reach /livewire/update, outside the admin's routes and their gate. */
    public function boot(AdminAccess $access): void
    {
        $this->access = $access;
        $access->authorizeScreen('pages.view');
    }

    public function show(string $report): void
    {
        $this->report = in_array($report, self::REPORTS, true) ? $report : 'broken';
        $this->start = 0;
    }

    public function previous(): void
    {
        $this->start = max(0, $this->start - self::PER_PAGE);
    }

    public function next(): void
    {
        $this->start += self::PER_PAGE;
    }

    /** The rebuild's dry run: how many pages' rows differ from their stored values. */
    public function checkIndex(): void
    {
        $this->authorizeManage();
        $totals = lynx()->run(RebuildPageReferences::class, dryRun: true)->arrayValue();
        $this->health = [
            'pages' => Value::int($totals['pages'] ?? 0),
            'added' => Value::int($totals['added'] ?? 0),
            'removed' => Value::int($totals['removed'] ?? 0),
            'resorted' => Value::int($totals['resorted'] ?? 0),
        ];
    }

    public function rebuild(): void
    {
        $this->authorizeManage();
        RebuildIndex::dispatch(Value::string(auth()->id()));
        $this->health = null;
        $this->notify(Value::string(__('lynx-admin::page_references.health.queued')));
    }

    public function render(ReportRows $rows): View
    {
        $this->report = in_array($this->report, self::REPORTS, true) ? $this->report : 'broken';
        $this->start = max(0, $this->start);
        $report = $rows->for($this->report, $this->access->viewScope(), self::PER_PAGE, $this->start);

        return view('lynx-admin::livewire.page-references', [
            'rows' => $report['rows'],
            'total' => $report['total'],
            'canManage' => $this->access->allows('page_references.manage'),
        ]);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->access->allows('page_references.manage'), 403);
    }
}
