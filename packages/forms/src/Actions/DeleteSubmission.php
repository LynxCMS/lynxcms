<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Forms\FormsFiles;
use LynxCms\Forms\Models\Submission;

/**
 * Deletes a submission and, once that commits, its files.
 */
final class DeleteSubmission extends Action
{
    use ResolvesSubmissions;

    public function __construct(private readonly FormsFiles $files) {}

    public static function name(): string
    {
        return 'forms.delete_submission';
    }

    public static function description(): string
    {
        return 'Delete a form submission and its files for good.';
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
        return ['submission' => ['required']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['submission' => $schema->string()->description('The submission\'s uuid or id.')->required()];
    }

    public function handle(Input $input, ActionContext $context): Submission
    {
        $submission = $this->submission($input->get('submission'));
        $submission->delete();
        $this->files->deleteAfterCommit($submission);
        $context->changes->deleted('form_submission', $submission->id, "Delete {$submission->form} submission {$submission->uuid}", ['form' => $submission->form]);

        return $submission;
    }
}
