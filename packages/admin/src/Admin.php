<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use Closure;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use LynxCms\Admin\Http\ModuleScreenController;
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
     * Route to controllers or screen(), not closures: route:cache can't store a closure defined
     * inside another closure on the same line.
     */
    public static function routes(Closure|string $routes): void
    {
        // Editors see every page: visibility rules are for visitors (ADR-0062).
        Route::middleware([...Value::strings(config('lynx-admin.middleware')), 'can:'.AdminServiceProvider::GATE, WithoutPageVisibility::class])
            ->prefix(Value::string(config('lynx-admin.prefix')))
            ->name('lynx.admin.')
            ->group($routes);
    }

    /**
     * A GET route showing $view to users with $permission, and 403 to others. Call it inside
     * routes(): Admin::screen('seo', 'lynx-seo::admin.index', 'seo.manage')->name('seo').
     *
     * @param  view-string  $view
     */
    public static function screen(string $uri, string $view, string $permission): RoutingRoute
    {
        return Route::get($uri, ModuleScreenController::class)
            ->defaults('view', $view)
            ->defaults('permission', $permission);
    }
}
