<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions;

use InvalidArgumentException;
use LynxCms\Contracts\Actions\Action as ActionContract;

/**
 * Every registered action. The MCP server and the AI agent generate their tools from here,
 * so a module that registers an action makes it available to agents automatically.
 *
 * @api
 */
final class ActionRegistry
{
    /** @var array<string, class-string<ActionContract>> */
    private array $actions = [];

    /**
     * @param  class-string<ActionContract>  ...$actions
     */
    public function register(string ...$actions): void
    {
        foreach ($actions as $action) {
            if (! is_subclass_of($action, ActionContract::class)) {
                throw new InvalidArgumentException("[{$action}] is not a Lynx action.");
            }

            $this->actions[$action::name()] = $action;
        }
    }

    /**
     * @return array<string, class-string<ActionContract>>
     */
    public function all(): array
    {
        ksort($this->actions);

        return $this->actions;
    }

    /**
     * @return array<string, class-string<ActionContract>>
     */
    public function tools(): array
    {
        return array_filter(
            $this->all(),
            fn (string $class): bool => ! is_subclass_of($class, Action::class) || $class::exposedAsTool(),
        );
    }

    /**
     * @return class-string<ActionContract>|null
     */
    public function find(string $name): ?string
    {
        return $this->actions[$name] ?? null;
    }
}
