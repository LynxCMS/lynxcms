<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Models\Setting;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\FormRecipients;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\Notifications\SubmissionDigest;
use LynxCms\Forms\SubmissionPresenter;

/**
 * Mails each form with a daily summary (Form::digest()) the submissions that arrived since the
 * last summary, spam left out, to the people who handle it (FormRecipients). The time of the last
 * summary is a setting, so a missed day is caught up and nothing is listed twice.
 *
 * @api
 */
final class SendDigest extends Action
{
    public const string SENT_SETTING = 'forms.digest_sent_at';

    public function __construct(private readonly FormRecipients $recipients, private readonly SubmissionPresenter $presenter) {}

    public static function name(): string
    {
        return 'forms.send_digest';
    }

    public static function description(): string
    {
        return 'Mail the daily summary of new submissions for the forms that send one instead of a mail per submission. Returns how many forms had new submissions.';
    }

    public static function permission(): string
    {
        return 'forms.manage';
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    /** @return int how many forms got a summary */
    public function handle(Input $input, ActionContext $context): int
    {
        $now = CarbonImmutable::now();
        $since = $this->since($now);
        $sent = 0;

        foreach (lynx()->forms()->all() as $key => $form) {
            $query = Submission::query()->where('form', (string) $key)->where('status', '!=', 'spam')->where('created_at', '>', $since)->where('created_at', '<=', $now);
            $count = $form->digest() ? $query->count() : 0;

            if ($count > 0) {
                $summaries = $query->orderByDesc('id')->limit(SubmissionDigest::LISTED)->get()->map(fn (Submission $s): string => $this->presenter->summary($s))->values()->all();
                $this->recipients->notify($form, new SubmissionDigest((string) $key, $form->text('label')->in(), $count, Value::strings($summaries)));
                $sent++;
            }
        }

        Setting::write(self::SENT_SETTING, $now->toIso8601String());
        $context->changes->updated('setting', null, "Send the daily form summaries ({$sent})", ['forms' => $sent]);

        return $sent;
    }

    /** The last summary, or a day ago the first time. */
    private function since(CarbonImmutable $now): CarbonImmutable
    {
        $last = Setting::read(self::SENT_SETTING);

        return $last === null ? $now->subDay() : CarbonImmutable::parse($last);
    }
}
