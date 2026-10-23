<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use LynxCms\Forms\Events\FormSubmitted;
use LynxCms\Forms\Models\Submission;

// #region listener
/**
 * Sends each course enquiry to the CRM as a lead, with the course it came from. Queued and retried,
 * so a slow or failing CRM never costs a lead.
 */
final class SendEnquiryToCrm implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function handle(FormSubmitted $event): void
    {
        // A copy without the service configured, such as a developer's machine: nothing to send.
        if (config('atlas.crm.url') === null) {
            return;
        }

        $submission = Submission::query()->find($event->submissionId);

        if ($submission === null || $submission->form !== 'enquiry') {
            return;
        }

        $course = $submission->page_id === null ? null : lynx()->pages->get($submission->page_id);

        Http::withToken((string) config('atlas.crm.token'))
            ->post(config('atlas.crm.url').'/leads', [
                'reference' => $submission->uuid,
                'name' => $submission->data['name'] ?? null,
                'email' => $submission->data['email'] ?? null,
                'phone' => $submission->data['phone'] ?? null,
                'goal' => $submission->data['goal'] ?? null,
                'course' => $course?->template()->name === 'course' ? (string) $course->getLanguageValue('en', 'title') : null,
                'language' => $submission->language()?->code,
            ])
            ->throw();
    }
}
// #endregion listener
