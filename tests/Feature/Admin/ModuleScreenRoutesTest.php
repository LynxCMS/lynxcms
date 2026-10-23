<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

/*
 * Admin screens added by modules survive php artisan route:cache. The route cache stores a
 * closure action by its line, so a closure route defined inside another closure on one line
 * came back as the outer closure (the page cache, page password and sitemap screens answered 500).
 * Controllers have no such problem, so Lynx routes use them.
 */

it('routes every Lynx address to a controller, not a closure', function (): void {
    $closures = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with((string) $route->getName(), 'lynx'))
        // A single closure on its line is cached correctly.
        ->reject(fn (Route $route): bool => $route->getName() === 'lynx.admin.home')
        ->filter(fn (Route $route): bool => $route->getAction('uses') instanceof Closure)
        ->map(fn (Route $route): string => (string) $route->getName())
        ->values()
        ->all();

    expect($closures)->toBe([]);
});

it('opens module screens for users with their permission', function (string $uri): void {
    courseSite();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $this->actingAs(new GenericUser(['id' => 1]));

    $this->get($uri)->assertOk();
})->with(['/lynx/page-cache', '/lynx/page-passwords', '/lynx/sitemap', '/lynx/seo', '/lynx/seo/audit', '/lynx/seo/structured-data', '/lynx/forms', '/lynx/forms/builder', '/lynx/static']);

it('refuses module screens to users without their permission', function (string $uri): void {
    courseSite();
    lynx()->run(SaveRole::class, ['name' => 'viewer', 'label' => 'Viewer', 'grants' => [['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['viewer']]);
    $this->actingAs(new GenericUser(['id' => 1]));

    $this->get($uri)->assertForbidden();
})->with(['/lynx/page-cache', '/lynx/page-passwords', '/lynx/sitemap', '/lynx/seo', '/lynx/forms', '/lynx/static']);
