<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use LynxCms\Forms\Events\FormSubmitted;
use LynxCms\Forms\Models\Submission;

// #region listener
/**
 * Sends each quote request to the CRM as a lead. Queued, so a slow CRM never slows the visitor,
 * and tried again later while the CRM is down.
 */
final class SendQuoteToCrm implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> seconds to wait before each new try */
    public array $backoff = [60, 300, 900, 3600];

    public function handle(FormSubmitted $event): void
    {
        $submission = Submission::query()->find($event->submissionId);

        // Other forms are not leads, and a submission deleted before the job ran has nothing to send.
        if ($submission === null || $submission->form !== 'quote') {
            return;
        }

        Http::withToken((string) config('services.crm.token'))
            ->post(config('services.crm.url').'/leads', [
                // The same reference on every try, so the CRM can ignore a lead it already has.
                'reference' => $submission->uuid,
                'email' => $submission->data['email'] ?? null,
                'type' => $submission->data['type'] ?? null,
                'cameras' => $submission->data['cameras'] ?? null,
                'language' => $submission->language()?->code,
                'files' => $submission->files->count(),
            ])
            ->throw();
    }
}
// #endregion listener
