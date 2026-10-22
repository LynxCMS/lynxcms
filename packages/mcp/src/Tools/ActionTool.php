<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use LynxCms\Contracts\Actions\Action;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Agents\AgentToolkit;

/**
 * One MCP tool per registered Lynx action, generated from the action registry (ADR-0015).
 * Every call goes through the ActionRunner: validation, hooks, dry runs and the activity log.
 */
final class ActionTool extends Tool
{
    use ActsForUser;

    /**
     * @param  class-string<Action>  $action
     */
    public function __construct(private readonly string $action) {}

    public function name(): string
    {
        return AgentToolkit::toolName($this->action::name());
    }

    public function title(): string
    {
        return $this->action::name();
    }

    public function description(): string
    {
        return app(AgentToolkit::class)->descriptionFor($this->action);
    }

    public function schema(JsonSchema $schema): array
    {
        return app(AgentToolkit::class)->schemaFor($this->action);
    }

    public function handle(Request $request): Response
    {
        $result = app(AgentToolkit::class)->run(
            $this->action,
            $request->all(),
            Actor::mcp($this->userId($request), ['tool' => $this->name()]),
        );

        return $result['ok'] ? Response::json($result) : Response::error(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
