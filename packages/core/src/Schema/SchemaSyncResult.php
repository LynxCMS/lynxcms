<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema;

/**
 * @api
 */
final class SchemaSyncResult
{
    /** @var list<string> */
    public array $changes = [];

    /** @var list<string> destructive changes that were not run because --force was not given */
    public array $skipped = [];

    /** @var list<string> */
    public array $errors = [];

    public bool $applied = false;

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function isEmpty(): bool
    {
        return $this->changes === [] && $this->skipped === [] && $this->errors === [];
    }
}
