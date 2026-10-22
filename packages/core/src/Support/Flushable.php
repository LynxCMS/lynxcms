<?php

declare(strict_types=1);

namespace LynxCms\Core\Support;

/**
 * Request-level caches that must be cleared after every outer action (commit or rollback).
 *
 * @internal
 */
interface Flushable
{
    public function flush(): void;
}
