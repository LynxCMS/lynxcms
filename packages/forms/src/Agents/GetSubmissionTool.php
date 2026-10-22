<?php

declare(strict_types=1);

namespace LynxCms\Forms\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Agents\AgentReadTool;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\SubmissionPresenter;

/**
 * One form submission for agents: every answer as the form asked it, file names, notes, the
 * page and language it came from.
 */
final class GetSubmissionTool extends AgentReadTool
{
    public function __construct(private readonly SubmissionPresenter $presenter) {}

    public static function name(): string
    {
        return 'get_submission';
    }

    public static function description(): string
    {
        return 'Read one form submission by uuid (from list_submissions): every answer with its label, file names, the team\'s notes, status, and the page and language it came from.';
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['submission' => $schema->string()->description('The submission\'s uuid.')->required()];
    }

    public static function permission(): string
    {
        return 'forms.view';
    }

    public function handle(array $arguments): mixed
    {
        $submission = Submission::query()->with('files')->where('uuid', Value::string($arguments['submission'] ?? ''))->first();

        if (! $submission instanceof Submission) {
            return ['error' => 'There is no such submission.'];
        }

        return [
            'uuid' => $submission->uuid,
            'form' => $submission->form,
            'status' => $submission->status,
            'received' => $submission->created_at->toIso8601String(),
            'language' => $submission->language()?->code,
            'page' => $submission->page_id === null ? null : lynx()->pages->get($submission->page_id)?->path(),
            'answers' => array_map(fn (array $line): array => ['label' => $line['label'], 'value' => $line['value']], $this->presenter->lines($submission)),
            'files' => $submission->files->pluck('name')->all(),
            'notes' => $submission->notes,
        ];
    }
}
