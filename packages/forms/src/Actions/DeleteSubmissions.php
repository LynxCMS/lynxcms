<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Forms\FormsFiles;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\Models\SubmissionFile;

/**
 * Deletes many submissions for good, with their files once that commits: a list, or every
 * submission a filter matches, a chunk at a time. One change in the activity log, with the counts.
 *
 * @api
 */
final class DeleteSubmissions extends Action
{
    use SelectsSubmissions;

    public function __construct(private readonly FormsFiles $files) {}

    public static function name(): string
    {
        return 'forms.delete_submissions';
    }

    public static function description(): string
    {
        return 'Delete many form submissions and their files for good: a list of uuids, or every submission a filter matches. Run it with dry_run first: it returns how many submissions and files would go.';
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
        return self::selectionRules();
    }

    public static function schema(JsonSchema $schema): array
    {
        return self::selectionSchema($schema);
    }

    /** @return array{submissions: int, files: int} */
    public function handle(Input $input, ActionContext $context): array
    {
        $submissions = 0;
        $files = 0;

        // chunkById pages by id (id > the last one), so deleting a chunk skips none of the next.
        $this->selected($input)->chunkById(self::CHUNK, function (Collection $chunk) use (&$submissions, &$files): void {
            $files += SubmissionFile::query()->whereIn('submission_id', $chunk->modelKeys())->count();
            Submission::query()->whereKey($chunk->modelKeys())->delete();
            $this->files->deleteAfterCommit(...$chunk->all());
            $submissions += $chunk->count();
        });

        if ($submissions > 0) {
            $context->changes->deleted('form_submission', null, "Delete {$submissions} submissions and {$files} files", ['count' => $submissions, 'files' => $files]);
        }

        return ['submissions' => $submissions, 'files' => $files];
    }
}
