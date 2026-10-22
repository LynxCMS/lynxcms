<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

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

        return Submission::query()->where('uuid', $reference)->orWhere('id', ctype_digit($reference) ? (int) $reference : 0)->first()
            ?? throw ValidationException::withMessages(['submission' => "There is no submission [{$reference}]."]);
    }
}
