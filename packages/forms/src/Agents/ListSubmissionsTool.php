<?php

declare(strict_types=1);

namespace LynxCms\Forms\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Agents\AgentReadTool;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\SubmissionPresenter;

/**
 * Form submissions for agents, newest first, each with its first answers: "summarise this
 * week's quote requests".
 */
final class ListSubmissionsTool extends AgentReadTool
{
    private const int DEFAULT_LIMIT = 20;

    private const int MAX_LIMIT = 100;

    private const int SUMMARY_LINES = 4;

    public function __construct(private readonly SubmissionPresenter $presenter) {}

    public static function name(): string
    {
        return 'list_submissions';
    }

    public static function description(): string
    {
        return 'List form submissions, newest first, with their first answers. Filter by form, status (new, read, archived, spam, or "open" for new and read) and date. get_submission reads one in full; forms_update_submission marks them.';
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'form' => $schema->string()->description('Form key, e.g. "quote".'),
            'status' => $schema->string()->enum(['open', 'new', 'read', 'archived', 'spam', 'all'])->description('Default: open.'),
            'since' => $schema->string()->description('Only submissions received on or after this date, e.g. "2026-10-01".'),
            'limit' => $schema->integer()->description('At most this many; default 20, up to 100.'),
        ];
    }

    public static function permission(): string
    {
        return 'forms.view';
    }

    public function handle(array $arguments): mixed
    {
        $status = Value::string($arguments['status'] ?? 'open', 'open');
        $query = Submission::query()->with('files')->orderByDesc('id')
            ->limit(min(self::MAX_LIMIT, max(1, Value::int($arguments['limit'] ?? null, self::DEFAULT_LIMIT))));

        match ($status) {
            'open' => $query->whereIn('status', ['new', 'read']),
            'all' => null,
            default => $query->where('status', $status),
        };

        if (Value::string($arguments['form'] ?? '') !== '') {
            $query->where('form', Value::string($arguments['form']));
        }

        if (Value::string($arguments['since'] ?? '') !== '') {
            $query->where('created_at', '>=', Value::string($arguments['since']));
        }

        return $query->get()->map(fn (Submission $submission): array => [
            'uuid' => $submission->uuid,
            'form' => $submission->form,
            'status' => $submission->status,
            'received' => $submission->created_at->toIso8601String(),
            'language' => $submission->language()?->code,
            'answers' => array_column(array_slice($this->presenter->lines($submission), 0, self::SUMMARY_LINES), 'value', 'label'),
        ])->all();
    }
}
