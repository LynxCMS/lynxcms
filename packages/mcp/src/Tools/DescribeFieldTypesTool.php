<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Override;

final class DescribeFieldTypesTool extends Tool
{
    #[Override]
    protected string $name = 'describe_field_types';

    #[Override]
    protected string $description = 'List field types with their description, whether they can be translatable, and the settings each accepts (rules such as max_length, min/max, format, allowed templates).';

    public function handle(Request $request): Response
    {
        return Response::json(lynx()->fields->types());
    }
}
