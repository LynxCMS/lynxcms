<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Forms\Models\Submission;

/**
 * Marks many submissions new, read, archived or spam in one go: a list, or every submission a
 * filter matches, a chunk at a time. One change in the activity log, with the count.
 *
 * @api
 */
final class UpdateSubmissions extends Action
{
    use SelectsSubmissions;

    public static function name(): string
    {
        return 'forms.update_submissions';
    }

    public static function description(): string
    {
        return 'Mark many form submissions as new, read, archived or spam: a list of uuids, or every submission a filter matches. Returns how many changed. Archived and spam, which the purge deletes later, need forms.manage.';
    }

    public static function permission(): string
    {
        return 'forms.view';
    }

    public static function rules(): array
    {
        return [...self::selectionRules(), 'status' => ['required', Rule::in(Submission::STATUSES)]];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [...self::selectionSchema($schema), 'status' => $schema->string()->enum(Submission::STATUSES)->required()];
    }

    /** @return int how many submissions changed */
    public function handle(Input $input, ActionContext $context): int
    {
        $status = (string) $input->string('status');

        // Archived and spam submissions are purged later: only forms.manage may send them there.
        if (in_array($status, Submission::PURGED_STATUSES, true)) {
            $context->authorize('forms.manage');
        }
        $reader = $context->actor->kind.($context->actor->id !== null ? ':'.$context->actor->id : '');
        $count = 0;

        $this->selected($input)->where('status', '!=', $status)->chunkById(self::CHUNK, function (Collection $chunk) use ($status, $reader, &$count): void {
            $ids = $chunk->modelKeys();
            Submission::query()->whereKey($ids)->update(['status' => $status]);

            if ($status === 'read') {
                Submission::query()->whereKey($ids)->whereNull('read_at')->update(['read_at' => now(), 'read_by' => $reader]);
            }

            $count += count($ids);
        });

        if ($count > 0) {
            $context->changes->updated('form_submission', null, "Mark {$count} submissions {$status}", ['count' => $count, 'status' => $status]);
        }

        return $count;
    }
}
