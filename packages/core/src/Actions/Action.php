<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions;

use LynxCms\Contracts\Actions\Action as ActionContract;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;

/**
 * Base class for Lynx actions.
 *
 * @api
 */
abstract class Action implements ActionContract
{
    /** permission() for actions anyone may run, such as switching one's own language. */
    public const string EVERYONE = '*';

    /**
     * The permission the runner checks before handle() (ADR-0033): a name from lynx()->permissions(),
     * several separated by "|" (any of them), EVERYONE, or null for superusers only, so an action
     * that declares nothing is closed. Page actions check the exact page and template inside.
     */
    public static function permission(): ?string
    {
        return null;
    }

    abstract public function handle(Input $input, ActionContext $context): mixed;

    public static function destructive(): bool
    {
        return false;
    }

    /** Whether runs of this action are written to the activity log. */
    public static function logsActivity(): bool
    {
        return true;
    }

    /**
     * Whether a run that changes several pages is logged as one event per page (ADR-0091), so
     * each page's history shows its part. Off by default: one event per run.
     */
    public static function logsActivityPerPage(): bool
    {
        return false;
    }

    /** Whether this action is exposed as an MCP and AI tool. */
    public static function exposedAsTool(): bool
    {
        return true;
    }
}
