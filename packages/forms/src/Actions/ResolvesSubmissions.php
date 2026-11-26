<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Models\Submission;

/**
 * Finds a submission by its uuid or id.
 *
 * @internal
 */
trait ResolvesSubmissions
{
    private function submission(mixed $reference): Submission
    {
        $reference = Value::string($reference);

        // PostgreSQL's uuid column refuses text that is no uuid, so each kind of reference has its own query.
        $submission = match (true) {
            Str::isUuid($reference) => Submission::query()->where('uuid', $reference)->first(),
            ctype_digit($reference) => Submission::query()->find((int) $reference),
            default => null,
        };

        return $submission
            ?? throw ValidationException::withMessages(['submission' => Value::string(__('lynx-forms::validation.submission', ['submission' => $reference]))]);
    }
}
