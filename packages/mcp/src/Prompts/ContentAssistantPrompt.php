<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use LynxCms\Core\Agents\AgentToolkit;
use Override;

final class ContentAssistantPrompt extends Prompt
{
    #[Override]
    protected string $name = 'content_assistant';

    #[Override]
    protected string $description = 'How to build and translate content on this Lynx site safely.';

    public function handle(Request $request): Response
    {
        return Response::text(AgentToolkit::usageGuide());
    }
}
