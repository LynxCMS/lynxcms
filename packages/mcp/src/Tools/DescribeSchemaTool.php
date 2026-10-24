<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use LynxCms\Core\Agents\AgentToolkit;
use Override;

final class DescribeSchemaTool extends Tool
{
    use ActsForUser;

    #[Override]
    protected string $name = 'describe_schema';

    #[Override]
    protected string $description = 'Describe the site: languages with fallback chains, templates and their fields, field types with their settings, registered components, and selector syntax. Call this first.';

    public function handle(Request $request, AgentToolkit $toolkit): Response
    {
        return $this->asUser($request, fn (): Response => Response::json($toolkit->describeSchema()));
    }
}
