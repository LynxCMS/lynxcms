<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor;

/**
 * One problem a doctor check found: what is wrong and, when there is one, how to fix it.
 *
 * @api
 */
final readonly class Finding
{
    public function __construct(public Severity $severity, public string $message, public ?string $fix = null) {}

    /**
     * @return array{severity: string, message: string, fix: string|null}
     */
    public function toArray(): array
    {
        return ['severity' => $this->severity->value, 'message' => $this->message, 'fix' => $this->fix];
    }
}
