<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Forms\Models\Submission;

/**
 * Sets a submission's status (new, read, archived, spam) and the editors' notes on it.
 */
final class UpdateSubmission extends Action
{
    use ResolvesSubmissions;

    public static function name(): string
    {
        return 'forms.update_submission';
    }

    public static function description(): string
    {
        return 'Mark a form submission as new, read, archived or spam, and keep notes on it.';
    }

    public static function permission(): string
    {
        return 'forms.view';
    }

    public static function rules(): array
    {
        return [
            'submission' => ['required'],
            'status' => ['sometimes', Rule::in(Submission::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'submission' => $schema->string()->description('The submission\'s uuid or id.')->required(),
            'status' => $schema->string()->enum(Submission::STATUSES),
            'notes' => $schema->string()->description('Notes for the team; null clears them.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Submission
    {
        $submission = $this->submission($input->get('submission'));

        if ($input->has('status')) {
            $submission->status = (string) $input->string('status');

            if ($submission->status === 'read' && $submission->read_at === null) {
                $submission->read_at = now();
                $submission->read_by = $context->actor->kind.($context->actor->id !== null ? ':'.$context->actor->id : '');
            }
        }

        if ($input->has('notes')) {
            $submission->notes = $input->string('notes');
        }

        $submission->save();
        $context->changes->updated('form_submission', $submission->id, "Update {$submission->form} submission {$submission->uuid}", $submission->only(['status', 'notes']));

        return $submission;
    }
}
