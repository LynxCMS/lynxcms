<?php

declare(strict_types=1);

namespace LynxCms\Mcp;

use Laravel\Mcp\Server;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Agents\ReadToolRegistry;
use LynxCms\Mcp\Prompts\ContentAssistantPrompt;
use LynxCms\Mcp\Resources\UsageGuideResource;
use LynxCms\Mcp\Tools\ActionTool;
use LynxCms\Mcp\Tools\DescribeFieldTypesTool;
use LynxCms\Mcp\Tools\DescribeSchemaTool;
use LynxCms\Mcp\Tools\FindPagesTool;
use LynxCms\Mcp\Tools\GetPageTool;
use LynxCms\Mcp\Tools\ModuleReadTool;
use LynxCms\Mcp\Tools\PageHistoryTool;
use Override;

/**
 * The Lynx MCP server. Read tools (Lynx's own and those modules register) plus one write tool per
 * registered action, so tools added by modules appear here automatically.
 */
class LynxServer extends Server
{
    #[Override]
    protected string $name = 'Lynx CMS';

    #[Override]
    protected string $version = '0.1.0';

    #[Override]
    protected string $instructions = 'Multilingual CMS. Call describe_schema first. Dry-run every write tool and show the changes to the user before applying. See the content_assistant prompt for details.';

    #[Override]
    protected array $resources = [UsageGuideResource::class];

    #[Override]
    protected array $prompts = [ContentAssistantPrompt::class];

    protected function boot(): void
    {
        $this->tools = [
            DescribeSchemaTool::class,
            DescribeFieldTypesTool::class,
            FindPagesTool::class,
            GetPageTool::class,
            PageHistoryTool::class,
            ...array_map(fn (string $tool): ModuleReadTool => new ModuleReadTool($tool), app(ReadToolRegistry::class)->all()),
            ...array_map(fn (string $action): ActionTool => new ActionTool($action), array_values(app(AgentToolkit::class)->writeTools())),
        ];
    }
}
