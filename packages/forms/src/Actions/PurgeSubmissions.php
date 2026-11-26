<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\FormsFiles;
use LynxCms\Forms\Models\Submission;

/**
 * Deletes spam and archived submissions older than some days (lynx-forms.purge_after_days), with
 * their files. New and read submissions are never purged.
 *
 * @api
 */
final class PurgeSubmissions extends Action
{
    public function __construct(private readonly FormsFiles $files) {}

    public static function name(): string
    {
        return 'forms.purge';
    }

    public static function description(): string
    {
        return 'Delete spam and archived form submissions older than some days, and submissions in any status older than their form\'s retention, with their files.';
    }

    public static function permission(): string
    {
        return 'forms.manage';
    }

    public static function destructive(): bool
    {
        return true;
    }

    public static function rules(): array
    {
        return ['older_than_days' => ['sometimes', 'integer', 'min:1']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['older_than_days' => $schema->integer()->description('Age in days. Defaults to the configured lynx-forms.purge_after_days.')];
    }

    /** @return int how many were deleted */
    public function handle(Input $input, ActionContext $context): int
    {
        return $this->purgeOld($input, $context) + $this->purgeRetained($context);
    }

    private function purgeOld(Input $input, ActionContext $context): int
    {
        $days = $input->int('older_than_days') ?? Value::int(config('lynx-forms.purge_after_days'), 180);
        $old = Submission::query()->whereIn('status', Submission::PURGED_STATUSES)->where('created_at', '<', now()->subDays($days))->get();

        if ($old->isEmpty()) {
            return 0;
        }

        Submission::query()->whereKey($old->modelKeys())->delete();
        $this->files->deleteAfterCommit(...$old->all());
        $context->changes->deleted('form_submission', null, "Purge {$old->count()} spam and archived submissions older than {$days} days", ['count' => $old->count()]);

        return $old->count();
    }

    /** Submissions in any status older than their form's retention (ADR-0147). */
    private function purgeRetained(ActionContext $context): int
    {
        $count = 0;

        foreach (lynx()->forms()->all() as $key => $form) {
            $days = $form->retentionDays();

            if ($days !== null) {
                // Whole days: everything received on or before the day $days + 1 days ago.
                $filter = ['show' => 'all', 'form' => (string) $key, 'to' => now()->subDays($days + 1)->toDateString()];
                $count += Value::int(Value::map($context->run(DeleteSubmissions::class, ['filter' => $filter]))['submissions'] ?? 0);
            }
        }

        return $count;
    }
}
