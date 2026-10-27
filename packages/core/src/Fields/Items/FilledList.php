<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A list that must hold at least one entry, failing with a message an editor understands
 * instead of the setting's dotted path.
 *
 * @internal
 */
final class FilledList implements ValidationRule
{
    /** Runs on missing and empty values too, which plain rules skip. */
    public bool $implicit = true;

    public function __construct(private readonly string $message) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            $fail($this->message);
        }
    }
}
