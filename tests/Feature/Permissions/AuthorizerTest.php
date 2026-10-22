<?php

declare(strict_types=1);

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Permissions\Role;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
});

/**
 * @param  array<string, array{templates?: list<string>|null, branches?: list<int>|null}>  $grants  permission => scope
 */
function grantRole(string $name, array $grants = [], bool $superuser = false): Role
{
    $role = Role::query()->create(['name' => $name, 'label' => ucfirst($name), 'is_superuser' => $superuser]);

    foreach ($grants as $permission => $scope) {
        $role->grants()->create(['permission' => $permission, 'templates' => $scope['templates'] ?? null, 'branches' => $scope['branches'] ?? null]);
    }

    return $role;
}

function isAllowed(Actor $actor, string $permission, ?Page $page = null): bool
{
    return app(Authorizer::class)->allows($actor, $permission, $page);
}

function pageAt(string $path): Page
{
    return lynx()->pages->get($path) ?? throw new RuntimeException("No page at {$path}.");
}

it('lets signed in users do everything until someone has a role', function (): void {
    expect(isAllowed(Actor::user(5), 'pages.delete'))->toBeTrue()
        ->and(isAllowed(Actor::mcp(), 'schema.manage'))->toBeTrue();
});

it('enforces roles once any user has one', function (): void {
    grantRole('writer', ['pages.edit' => []])->assign(5);

    expect(isAllowed(Actor::user(5), 'pages.edit'))->toBeTrue()
        ->and(isAllowed(Actor::user(5), 'pages.delete'))->toBeFalse()
        ->and(isAllowed(Actor::user(6), 'pages.edit'))->toBeFalse();
});

it('lets superusers do everything', function (): void {
    grantRole('admins', superuser: true)->assign(1);

    expect(isAllowed(Actor::user(1), 'schema.manage'))->toBeTrue()
        ->and(isAllowed(Actor::user(1), 'pages.delete', pageAt('/courses/alpha/')))->toBeTrue();
});

it('adds up the grants of every role a user has', function (): void {
    grantRole('writer', ['pages.edit' => []])->assign(5);
    grantRole('publisher', ['pages.publish' => []])->assign(5);

    expect(isAllowed(Actor::user(5), 'pages.edit'))->toBeTrue()
        ->and(isAllowed(Actor::user(5), 'pages.publish'))->toBeTrue();
});

it('limits grants to templates', function (): void {
    grantRole('course-editor', ['pages.edit' => ['templates' => ['course']]])->assign(5);

    expect(isAllowed(Actor::user(5), 'pages.edit', pageAt('/courses/alpha/')))->toBeTrue()
        ->and(isAllowed(Actor::user(5), 'pages.edit', pageAt('/courses/')))->toBeFalse()
        ->and(isAllowed(Actor::user(5), 'pages.edit'))->toBeTrue();
});

it('limits grants to branches of the tree, the branch page included', function (): void {
    grantRole('courses-editor', ['pages.edit' => ['branches' => [pageAt('/courses/')->id]]])->assign(5);

    expect(isAllowed(Actor::user(5), 'pages.edit', pageAt('/courses/alpha/')))->toBeTrue()
        ->and(isAllowed(Actor::user(5), 'pages.edit', pageAt('/courses/')))->toBeTrue()
        ->and(isAllowed(Actor::user(5), 'pages.edit', pageAt('/')))->toBeFalse();
});

it('gives the console and the system every right', function (): void {
    grantRole('writer', ['pages.edit' => []])->assign(5);

    expect(isAllowed(Actor::cli(), 'schema.manage'))->toBeTrue()
        ->and(isAllowed(Actor::system(), 'system.manage'))->toBeTrue();
});

it('lets MCP and agents act with their user\'s roles, and no others', function (): void {
    grantRole('writer', ['pages.edit' => []])->assign(5);

    expect(isAllowed(Actor::mcp(5), 'pages.edit'))->toBeTrue()
        ->and(isAllowed(Actor::ai(5), 'pages.delete'))->toBeFalse()
        ->and(isAllowed(Actor::mcp(), 'pages.edit'))->toBeFalse();
});

it('gives anonymous MCP clients the configured role', function (): void {
    grantRole('writer', ['pages.edit' => []])->assign(5);
    grantRole('reader', ['pages.view' => []]);
    config(['lynx.permissions.anonymous_role' => 'reader']);

    expect(isAllowed(Actor::mcp(), 'pages.view'))->toBeTrue()
        ->and(isAllowed(Actor::mcp(), 'pages.edit'))->toBeFalse();
});

it('knows the core permissions and the ones modules add', function (): void {
    lynx()->permissions()->register('reports.view', 'View reports');

    expect(lynx()->permissions()->has('pages.edit'))->toBeTrue()
        ->and(lynx()->permissions()->isScoped('pages.edit'))->toBeTrue()
        ->and(lynx()->permissions()->isScoped('schema.manage'))->toBeFalse()
        ->and(lynx()->permissions()->all())->toHaveKey('reports.view');
});
