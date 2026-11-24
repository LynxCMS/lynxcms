<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Saving;

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionRunner;

/**
 * Who may write a locked field (ADR-0149): the site's code, running as the command line (Artisan,
 * queued jobs, the scheduler, Tinker) or as the system (code that runs
 * ActionRunner::actingAs(Actor::system(), ...), such as a webhook's controller). People in the
 * admin, MCP clients and the agent may not.
 *
 * @internal
 */
final readonly class LockedFields
{
    public function __construct(private ActionRunner $runner) {}

    public function mayWrite(?Actor $actor = null): bool
    {
        return in_array(($actor ?? $this->runner->actor())->kind, [Actor::CLI, Actor::SYSTEM], true);
    }
}
