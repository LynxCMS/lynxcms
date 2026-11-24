<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Admin\Livewire\Concerns\ActsOnSubmissions;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\SubmissionFilter;
use LynxCms\Forms\SubmissionPresenter;

/**
 * The submissions inbox: filtered by form, status, text and when they arrived, newest first, a
 * numbered page at a time, with bulk actions on the selection (ActsOnSubmissions).
 *
 * @internal
 */
final class Inbox extends Component
{
    use ActsOnSubmissions;
    use Notifies;

    public const array PAGE_SIZES = [25, 50, 100];

    /** Periods the date filter offers, besides any time ("") and custom dates. */
    public const array PERIODS = ['7_days', '30_days', 'this_month', 'last_month', 'custom'];

    /** Pages linked on either side of the current one. */
    private const int PAGE_WINDOW = 2;

    /** "open" (new and read), a status, or "all". */
    #[Url]
    public string $show = 'open';

    #[Url]
    public string $form = '';

    #[Url]
    public string $search = '';

    /** "" for any time, a period of PERIODS, or "custom" with $from and $to. */
    #[Url]
    public string $period = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public int $page = 1;

    #[Url(as: 'per')]
    public int $perPage = self::PAGE_SIZES[0];

    /** The latest notice, for Notifies. */
    public ?string $status = null;

    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('forms.view');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['show', 'form', 'search', 'period', 'from', 'to', 'perPage'], true)) {
            $this->page = 1;
            $this->clearSelection();
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /** Every submission, in any status: what an empty filtered list offers. */
    public function clearFilters(): void
    {
        $this->reset('form', 'search', 'period', 'from', 'to');
        $this->show = 'all';
        $this->page = 1;
        $this->clearSelection();
    }

    public function render(): View
    {
        $query = $this->filter()->query();
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $this->pageSize()));
        $this->page = min($this->page, $lastPage);

        return view('lynx-forms::admin.livewire.inbox', [
            'submissions' => $query->with('files')->orderByDesc('id')->forPage($this->page, $this->pageSize())->get(),
            'forms' => array_map(fn (Form $form): string => $form->text('label')->in(), lynx()->forms()->all()),
            'total' => $total,
            'lastPage' => $lastPage,
            'pages' => $this->pageLinks($lastPage),
            'hasAny' => $total > 0 || Submission::query()->exists(),
            'isPageSelected' => $this->isPageSelected(),
            'canDelete' => app(AdminAccess::class)->allows('forms.manage'),
            'exportQuery' => array_diff_key($this->filter()->toArray(), ['form' => true]),
        ]);
    }

    /** "Ana Lima · ana@example.com": the first short answers, to tell rows apart. */
    public function summaryOf(Submission $submission): string
    {
        return (new SubmissionPresenter)->summary($submission);
    }

    protected function filter(): SubmissionFilter
    {
        [$from, $to] = $this->dates();

        return new SubmissionFilter($this->show, $this->form, $this->search, $from, $to);
    }

    /** @return list<string> */
    protected function pageUuids(): array
    {
        return Value::strings($this->filter()->query()->orderByDesc('id')->forPage($this->page, $this->pageSize())->pluck('uuid')->all());
    }

    private function isPageSelected(): bool
    {
        $uuids = $this->pageUuids();

        return $this->allMatching || ($uuids !== [] && array_diff($uuids, $this->selected) === []);
    }

    private function pageSize(): int
    {
        return in_array($this->perPage, self::PAGE_SIZES, true) ? $this->perPage : self::PAGE_SIZES[0];
    }

    /**
     * The period as dates, in the app's time zone.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function dates(): array
    {
        $now = CarbonImmutable::now();
        $date = fn (string $value): ?CarbonImmutable => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::parse($value) : null;

        return match ($this->period) {
            '7_days' => [$now->subDays(6)->startOfDay(), null],
            '30_days' => [$now->subDays(29)->startOfDay(), null],
            'this_month' => [$now->startOfMonth(), null],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'custom' => [$date($this->from)?->startOfDay(), $date($this->to)?->endOfDay()],
            default => [null, null],
        };
    }

    /**
     * The first, the last, and the pages around the current one; null marks a gap.
     *
     * @return list<int|null>
     */
    private function pageLinks(int $lastPage): array
    {
        $links = [];

        foreach (range(1, $lastPage) as $page) {
            $isShown = $page === 1 || $page === $lastPage || abs($page - $this->page) <= self::PAGE_WINDOW;

            if ($isShown) {
                $links[] = $page;
            } elseif (end($links) !== null) {
                $links[] = null;
            }
        }

        return $links;
    }
}
