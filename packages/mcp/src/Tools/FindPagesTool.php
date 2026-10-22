<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Support\Value;
use Override;

final class FindPagesTool extends Tool
{
    use ActsForUser;

    #[Override]
    protected string $name = 'find_pages';

    #[Override]
    protected string $description = 'Find pages with a selector, e.g. "template=course, title%=risk, sort=-title". Returns id, template, title and URL.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'selector' => $schema->string()->description('Selector string.')->required(),
            'language' => $schema->string()->description('Language code for matching and titles. Default: current language.'),
            'limit' => $schema->integer()->description('Maximum pages to return. Default and upper bound are set by the site (usually 25 and 100); use start= in the selector to page.'),
        ];
    }

    public function handle(Request $request, AgentToolkit $toolkit): Response
    {
        return $this->asUser($request, function () use ($request, $toolkit): Response {
            try {
                return Response::json($toolkit->findPages(Value::string($request->get('selector')), Value::nullableString($request->get('language')), Value::nullableInt($request->get('limit'))));
            } catch (SelectorException $e) {
                return Response::error($e->getMessage());
            }
        });
    }
}
