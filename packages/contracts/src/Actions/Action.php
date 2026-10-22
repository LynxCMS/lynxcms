<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * A single write operation. Every write in Lynx is an action run through the ActionRunner,
 * so hooks, dry runs, the activity log, MCP tools and AI tools all work the same way.
 *
 * Implementations also define: handle(Input $input, ActionContext $context): mixed
 *
 * @api
 */
interface Action
{
    /** Stable machine name, e.g. "languages.create". Used for MCP and AI tool names. */
    public static function name(): string;

    /** One or two sentences an AI agent or a developer can read to know what the action does. */
    public static function description(): string;

    /**
     * Laravel validation rules for the input array.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array;

    /**
     * JSON schema of the input, used by the MCP server and the AI agent.
     *
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array;

    /** Whether the action can remove data. Destructive actions are flagged in plans. */
    public static function destructive(): bool;
}
