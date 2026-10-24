<?php

declare(strict_types=1);

namespace LynxCms\Ai;

use RuntimeException;

/**
 * An agent run passed lynx.ai.plan_seconds: it holds a database transaction (other writers wait
 * on SQLite), so it stops at its next tool call and everything it did rolls back.
 *
 * @api
 */
final class PlanTookTooLong extends RuntimeException
{
    public static function after(int $seconds): self
    {
        return new self("The agent took more than {$seconds} seconds, so nothing it did was kept. Ask for less at once, or raise lynx.ai.plan_seconds.");
    }
}
