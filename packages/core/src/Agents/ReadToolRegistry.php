<?php

declare(strict_types=1);

namespace LynxCms\Core\Agents;

use InvalidArgumentException;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Permissions\Authorizer;

/**
 * Read tools modules add for MCP clients and the AI agent (AgentReadTool). Running one checks
 * its permission for whoever acts (the MCP client's user, the agent's user).
 *
 * @api
 */
final class ReadToolRegistry
{
    /** @var list<class-string<AgentReadTool>> */
    private array $tools = [];

    /** @param class-string<AgentReadTool> ...$tools */
    public function register(string ...$tools): void
    {
        foreach ($tools as $tool) {
            if (! is_subclass_of($tool, AgentReadTool::class)) {
                throw new InvalidArgumentException("[{$tool}] is not an AgentReadTool.");
            }

            if (! in_array($tool, $this->tools, true)) {
                $this->tools[] = $tool;
            }
        }
    }

    /** @return list<class-string<AgentReadTool>> */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * @param  class-string<AgentReadTool>  $tool
     * @param  array<string, mixed>  $arguments
     */
    public function run(string $tool, array $arguments): mixed
    {
        $permission = $tool::permission();

        if ($permission !== null) {
            app(Authorizer::class)->authorize(app(ActionRunner::class)->actor(), $permission);
        }

        $instance = app()->make($tool);

        if (! $instance instanceof AgentReadTool) {
            throw new InvalidArgumentException("[{$tool}] is not an AgentReadTool.");
        }

        return $instance->handle($arguments);
    }
}
