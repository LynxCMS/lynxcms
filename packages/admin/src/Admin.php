<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use Closure;
use Illuminate\Support\Facades\Route;
use LynxCms\Core\Http\Middleware\WithoutPageVisibility;
use LynxCms\Core\Support\Value;

/**
 * Entry points for modules that add admin screens.
 *
 * @api
 */
final class Admin
{
    /**
     * Register routes under the admin prefix, middleware, access gate and "lynx.admin." names.
     */
    public static function routes(Closure|string $routes): void
    {
        // Editors see every page: visibility rules are for visitors (ADR-0062).
        Route::middleware([...Value::strings(config('lynx-admin.middleware')), 'can:'.AdminServiceProvider::GATE, WithoutPageVisibility::class])
            ->prefix(Value::string(config('lynx-admin.prefix')))
            ->name('lynx.admin.')
            ->group($routes);
    }
}
