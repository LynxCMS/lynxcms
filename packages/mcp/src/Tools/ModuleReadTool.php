<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use LynxCms\Core\Agents\AgentReadTool;
use LynxCms\Core\Agents\ReadToolRegistry;

/**
 * A module's read tool (AgentReadTool) as an MCP tool, run as the client's user.
 */
final class ModuleReadTool extends Tool
{
    use ActsForUser;

    /**
     * @param  class-string<AgentReadTool>  $tool
     */
    public function __construct(private readonly string $tool) {}

    public function name(): string
    {
        return $this->tool::name();
    }

    public function description(): string
    {
        return $this->tool::description();
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->tool::schema($schema);
    }

    public function handle(Request $request): Response
    {
        return $this->asUser($request, fn (): Response => Response::json(app(ReadToolRegistry::class)->run($this->tool, $request->all())));
    }
}
