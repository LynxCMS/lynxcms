<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin;

use Closure;
use Illuminate\Support\Facades\View;
use LynxCms\Admin\Home\HomeScreen;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\SubmissionPresenter;

/**
 * The admin home screen's panel of new form submissions, and the stat of last month's, for those
 * who may see them.
 *
 * @internal
 */
final class FormsHomePanel
{
    public const string VIEW = 'lynx-forms::admin.home-panel';

    public const int LIMIT = 5;

    /** The stat counts the submissions of the last month. */
    private const int STAT_DAYS = 30;

    /** @param  Closure(): bool  $canSee */
    public static function register(Closure $canSee): void
    {
        $home = app(HomeScreen::class);
        $home->panel(self::VIEW, $canSee, order: 20);
        $home->stat('form-submissions', 'lynx-forms::admin.home.stat', fn (): int => Submission::query()->where('created_at', '>=', now()->subDays(self::STAT_DAYS))->count(),
            url: fn (): string => route('lynx.admin.forms'), icon: 'inbox', visible: $canSee, order: 40);
        View::composer(self::VIEW, fn ($view) => $view->with(self::data()));
    }

    /**
     * @return array{total: int, submissions: list<array{summary: string, form: string, url: string, date: string, at: string}>}
     */
    public static function data(): array
    {
        $query = Submission::query()->where('status', 'new');
        $forms = array_map(fn (Form $form): string => $form->text('label')->in(), lynx()->forms()->all());
        $presenter = new SubmissionPresenter;
        $submissions = [];

        foreach ((clone $query)->orderByDesc('id')->limit(self::LIMIT)->get() as $submission) {
            $submissions[] = [
                'summary' => $presenter->summary($submission) ?: __('lynx-forms::admin.inbox.submission', ['id' => $submission->id]),
                'form' => $forms[$submission->form] ?? $submission->form,
                'url' => route('lynx.admin.forms.show', $submission->uuid),
                'date' => $submission->created_at->isoFormat($submission->created_at->isCurrentYear() ? 'D MMM' : 'D MMM YYYY'),
                'at' => $submission->created_at->toDayDateTimeString(),
            ];
        }

        return ['total' => $query->count(), 'submissions' => $submissions];
    }
}
