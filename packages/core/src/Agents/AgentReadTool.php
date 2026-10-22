<?php

declare(strict_types=1);

namespace LynxCms\Core\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * A read-only tool a module offers to MCP clients and the AI agent, next to Lynx's own
 * (find_pages, get_page…). Writes stay actions. Register it with
 * app(ReadToolRegistry::class)->register(MyTool::class); the MCP server and the agent pick it up.
 *
 * @api
 */
abstract class AgentReadTool
{
    /** snake_case, e.g. "list_submissions". */
    abstract public static function name(): string;

    abstract public static function description(): string;

    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    /** The permission the acting user needs, or null when reading needs none. */
    public static function permission(): ?string
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    abstract public function handle(array $arguments): mixed;
}
