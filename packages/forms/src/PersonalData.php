<?php

declare(strict_types=1);

namespace LynxCms\Forms;

use LynxCms\Forms\Models\Submission;

/**
 * What the site holds about one person from its forms (ADR-0147): every submission, in any
 * status and form, where their email address is a whole answer. For a request under data
 * protection law (the GDPR's right of access and to erasure): export it all, or delete it.
 *
 * @api
 */
final readonly class PersonalData
{
    public function __construct(private SubmissionPresenter $presenter) {}

    public function filter(string $email): SubmissionFilter
    {
        return new SubmissionFilter(show: 'all', email: $email);
    }

    /**
     * The person's submissions as data to hand them, oldest first: form, when, status, language,
     * page, answers as the form asked them, and the names of the files they sent.
     *
     * @return array{email: string, exported_at: string, submissions: list<array<string, mixed>>}
     */
    public function export(string $email): array
    {
        $submissions = [];

        foreach ($this->filter($email)->query()->with('files')->orderBy('id')->lazyById() as $submission) {
            if ($submission instanceof Submission) {
                $submissions[] = $this->entry($submission);
            }
        }

        return ['email' => $email, 'exported_at' => now()->toIso8601String(), 'submissions' => $submissions];
    }

    /** @return array<string, mixed> */
    private function entry(Submission $submission): array
    {
        return [
            'form' => $submission->form,
            'received_at' => $submission->created_at->toIso8601String(),
            'status' => $submission->status,
            'language' => $submission->language()?->code,
            'page' => $submission->page_id,
            'answers' => array_map(fn (array $line): array => ['question' => $line['label'], 'answer' => $line['value']], $this->presenter->lines($submission)),
            'files' => $submission->files->pluck('name')->values()->all(),
        ];
    }
}
