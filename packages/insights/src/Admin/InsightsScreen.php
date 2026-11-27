<?php

declare(strict_types=1);

namespace LynxCms\Insights\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\ArchiveInsight;
use LynxCms\Insights\Actions\DismissInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Actions\StopInsight;
use LynxCms\Insights\Models\InsightReport;

/**
 * The Insights screen (ADR-0155): one report's insights, heaviest first, found by a search and
 * filtered by state (open by default), source and report, all kept in the address. With
 * insights.review, an editor reviews an insight's plan as a dry run, then approves, dismisses or
 * archives it, or writes a report now. Each runs through the insights actions, so
 * approving needs the permissions of every action in the plan.
 *
 * @internal
 */
final class InsightsScreen extends Component
{
    use Notifies;

    /** The last notice, for screen readers and tests (Notifies). */
    public ?string $status = null;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'open')]
    public string $state = 'open';

    #[Url(except: '')]
    public string $source = '';

    /** The report shown; the latest when null or unknown. */
    #[Url(except: null)]
    public ?int $report = null;

    /** The insight whose plan is under review. */
    #[Locked]
    public ?int $reviewing = null;

    /** Every request, updates included: they reach /livewire/update, outside the admin's routes and their gate. */
    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('insights.view');
    }

    /** Approving as a dry run: refuses as approving would; the view then lists the plan's steps. */
    public function review(int $insight): void
    {
        $this->closeReview();
        lynx()->run(ApproveInsight::class, ['insight' => $insight], dryRun: true);
        $this->reviewing = $insight;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'state', 'source');
    }

    public function closeReview(): void
    {
        $this->reviewing = null;
    }

    public function approve(int $insight): void
    {
        $this->decide(ApproveInsight::class, $insight, 'started');
    }

    public function dismiss(int $insight): void
    {
        $this->decide(DismissInsight::class, $insight, 'dismissed');
    }

    public function stop(int $insight): void
    {
        $this->decide(StopInsight::class, $insight, 'stopped');
    }

    public function archive(int $insight): void
    {
        $this->decide(ArchiveInsight::class, $insight, 'archived');
    }

    public function generate(): void
    {
        $this->closeReview();
        $this->report = null;
        $report = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class);
        $count = $report->insights()->count();
        $this->notify(trans_choice('lynx-insights::admin.generated', $count, ['count' => $count]));
    }

    public function render(ReportView $view, AdminAccess $access): View
    {
        $report = $view->report($this->report);
        $filter = new InsightFilter($this->state, $this->source, $this->search);
        $shown = $report instanceof InsightReport ? $view->shown($report, $filter) : ['insights' => [], 'pages' => []];

        return view('lynx-insights::admin.screen', [
            'shownReport' => $report,
            'insights' => $shown['insights'],
            'pages' => $shown['pages'],
            'filter' => $filter,
            'reports' => $report instanceof InsightReport ? $view->reports() : [],
            'counts' => $report instanceof InsightReport ? $view->counts($report) : [],
            'sources' => $report instanceof InsightReport ? $view->sources($report) : [],
            'labels' => $view->labels(),
            'isRunning' => $report instanceof InsightReport && $view->isRunning($report),
            'canReview' => $access->allows('insights.review'),
            // A failed step's stack trace can show paths and pieces of arguments: superusers only.
            'showsTraces' => $access->isSuperuser(),
        ]);
    }

    /**
     * @param  class-string<ApproveInsight|DismissInsight|ArchiveInsight|StopInsight>  $action
     */
    private function decide(string $action, int $insight, string $done): void
    {
        lynx()->run($action, ['insight' => $insight]);
        $this->closeReview();
        $this->notify(Value::string(__('lynx-insights::admin.'.$done)));
    }
}
