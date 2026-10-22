<?php

declare(strict_types=1);

namespace LynxCms\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use LynxCms\Ai\AgentSession;
use LynxCms\Contracts\Actions\Action;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Support\Value;

/**
 * A Lynx action as a laravel/ai tool, generated from the same registry as the MCP tools.
 * Every successful call is recorded in the session so the plan can be shown and replayed.
 */
final readonly class ActionTool implements Tool
{
    /**
     * @param  class-string<Action>  $action
     */
    public function __construct(
        private string $action,
        private AgentSession $session,
        private AgentToolkit $toolkit,
    ) {}

    public function name(): string
    {
        return AgentToolkit::toolName($this->action::name());
    }

    public function description(): string
    {
        return $this->toolkit->descriptionFor($this->action);
    }

    public function schema(JsonSchema $schema): array
    {
        $properties = $this->toolkit->schemaFor($this->action);
        unset($properties['dry_run']); // the session decides; the plan is reviewed as a whole

        return $properties;
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();
        unset($arguments['dry_run']);

        $result = $this->toolkit->run($this->action, $arguments, $this->session->actor);

        if ($result['ok']) {
            $value = $result['result'];
            $pageId = is_array($value) && isset($value['id'], $value['template']) ? Value::int($value['id']) : null;
            $this->session->record($this->action, $this->name(), $arguments, $result['changes'], $result['destructive'], $pageId);
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
