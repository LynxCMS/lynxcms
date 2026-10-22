<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use LynxCms\Core\Agents\AgentToolkit;
use Override;

final class UsageGuideResource extends Resource
{
    #[Override]
    protected string $name = 'usage_guide';

    #[Override]
    protected string $description = 'Usage guide for agents working on a Lynx site.';

    #[Override]
    protected string $uri = 'lynx://usage-guide';

    #[Override]
    protected string $mimeType = 'text/markdown';

    public function handle(Request $request): Response
    {
        return Response::text(AgentToolkit::usageGuide());
    }
}
