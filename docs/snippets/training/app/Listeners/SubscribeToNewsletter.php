<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use LynxCms\Forms\Events\FormSubmitted;
use LynxCms\Forms\Models\Submission;

// #region listener
/**
 * Adds newsletter sign-ups to the mailing list, tagged with their language so Portuguese readers
 * get the Portuguese edition. The mailing service sends its own confirmation email (double opt-in).
 */
final class SubscribeToNewsletter implements ShouldQueue
{
    public int $tries = 5;

    public function handle(FormSubmitted $event): void
    {
        // A copy without the service configured, such as a developer's machine: nothing to send.
        if (config('atlas.newsletter.url') === null) {
            return;
        }

        $submission = Submission::query()->find($event->submissionId);

        if ($submission === null || $submission->form !== 'newsletter') {
            return;
        }

        Http::withToken((string) config('atlas.newsletter.token'))
            ->post(config('atlas.newsletter.url').'/subscribers', [
                'email' => $submission->data['email'] ?? null,
                'tags' => ['website', 'lang-'.($submission->language()?->code ?? 'en')],
                'double_opt_in' => true,
            ])
            ->throw();
    }
}
// #endregion listener
