<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use LynxCms\Core\Agents\PageHistory;
use LynxCms\Core\Support\Value;
use Override;

final class PageHistoryTool extends Tool
{
    use ActsForUser;

    #[Override]
    protected string $name = 'get_page_history';

    #[Override]
    protected string $description = 'A page\'s old URLs (and whether each still redirects), the pages that reference it (menu items included), its latest activity and revisions (ids for pages_restore_revision), and whether it has a draft. Check it before renaming, moving or deleting a page.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Page id, default-language path such as "/courses/delivery-8/", or page name.')->required(),
            'activity_limit' => $schema->integer()->description('How many activity entries to return, newest first. Default 20.'),
        ];
    }

    public function handle(Request $request, PageHistory $history): Response
    {
        return $this->asUser($request, function () use ($request, $history): Response {
            $report = $history->for(Value::string($request->get('page')), max(1, Value::int($request->get('activity_limit'), 20)));

            return $report === null ? Response::error('Page not found.') : Response::json($report);
        });
    }
}
