<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Support\Value;
use Override;

final class GetPageTool extends Tool
{
    use ActsForUser;

    #[Override]
    protected string $name = 'get_page';

    #[Override]
    protected string $description = 'Read one page by id, default-language path or page name: template, names per language, field values in the requested language (with fallback info) and every stored value per language.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Page id or path such as "/courses/delivery-8/".')->required(),
            'language' => $schema->string()->description('Language code for the resolved values.'),
        ];
    }

    public function handle(Request $request, AgentToolkit $toolkit): Response
    {
        return $this->asUser($request, function () use ($request, $toolkit): Response {
            $page = $toolkit->getPage(Value::string($request->get('page')), Value::nullableString($request->get('language')));

            return $page === null ? Response::error('Page not found.') : Response::json($page);
        });
    }
}
