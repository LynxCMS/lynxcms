<?php

declare(strict_types=1);

namespace LynxCms\Insights\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Actions\Runner\Change;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\ArchiveInsight;
use LynxCms\Insights\Actions\DismissInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Models\InsightReport;

/**
 * The Insights screen (ADR-0155): the latest report, heaviest insight first, and the earlier
 * reports. With insights.review, an editor reviews an insight's plan as a dry run, then approves,
 * dismisses or archives it, or writes a report now. Each runs through the insights actions, so
 * approving needs the permissions of every action in the plan.
 *
 * @internal
 */
final class InsightsScreen extends Component
{
    use Notifies;

    /** The last notice, for screen readers and tests (Notifies). */
    public ?string $status = null;

    /** The insight whose plan is under review. */
    #[Locked]
    public ?int $reviewing = null;

    /** @var list<string> what approving the reviewed insight would change */
    #[Locked]
    public array $preview = [];

    /** Every request, updates included: they reach /livewire/update, outside the admin's routes and their gate. */
    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('insights.view');
    }

    /** Approving as a dry run: lists what the plan would change, and refuses as approving would. */
    public function review(int $insight): void
    {
        $this->closeReview();
        $changes = lynx()->run(ApproveInsight::class, ['insight' => $insight], dryRun: true)->changes->all();
        $this->preview = array_values(array_map(fn (Change $change): string => $change->summary, array_filter($changes, fn (Change $change): bool => $change->subject !== 'insight')));
        $this->reviewing = $insight;
    }

    public function closeReview(): void
    {
        $this->reviewing = null;
        $this->preview = [];
    }

    public function approve(int $insight): void
    {
        $this->decide(ApproveInsight::class, $insight, 'approved');
    }

    public function dismiss(int $insight): void
    {
        $this->decide(DismissInsight::class, $insight, 'dismissed');
    }

    public function archive(int $insight): void
    {
        $this->decide(ArchiveInsight::class, $insight, 'archived');
    }

    public function generate(): void
    {
        $this->closeReview();
        $report = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class);
        $count = $report->insights()->count();
        $this->notify(trans_choice('lynx-insights::admin.generated', $count, ['count' => $count]));
    }

    public function render(ReportView $view, AdminAccess $access): View
    {
        $latest = $view->latest();
        $insights = $latest instanceof InsightReport ? $view->insights($latest) : [];

        return view('lynx-insights::admin.screen', [
            'report' => $latest,
            'insights' => $insights,
            'pages' => $view->pages($insights),
            'earlier' => $latest instanceof InsightReport ? $view->earlier($latest) : [],
            'canReview' => $access->allows('insights.review'),
        ]);
    }

    /**
     * @param  class-string<ApproveInsight|DismissInsight|ArchiveInsight>  $action
     */
    private function decide(string $action, int $insight, string $done): void
    {
        lynx()->run($action, ['insight' => $insight]);
        $this->closeReview();
        $this->notify(Value::string(__('lynx-insights::admin.'.$done)));
    }
}
