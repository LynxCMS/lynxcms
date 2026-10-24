<?php

declare(strict_types=1);

namespace LynxCms\Mcp;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use LynxCms\Core\Http\Middleware\WithoutPageVisibility;
use LynxCms\Core\Support\Value;
use LynxCms\Mcp\Http\RequireTokenAbility;

final class McpServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Local (stdio) server: php artisan mcp:start lynx
        Mcp::local('lynx', LynxServer::class);

        // HTTP server for remote clients, behind the app's auth middleware.
        if (config('lynx.mcp.web.enabled', false)) {
            Mcp::web(Value::string(config('lynx.mcp.web.path', '/mcp/lynx')), LynxServer::class)
                ->middleware([...Value::strings(config('lynx.mcp.web.middleware', ['auth:sanctum'])), RequireTokenAbility::class, WithoutPageVisibility::class]);
        }
    }
}
