<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * Every admin screen checks access the same way, in boot(), which Livewire runs on its own update
 * requests too: the admin gate first (an app may narrow it), then the screen's permission, or
 * superuser for screens only superusers use.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['writer']]);
});

it('lets a user with the permission open the screen', function (): void {
    $this->actingAs(new GenericUser(['id' => 5]));

    app(AdminAccess::class)->authorizeScreen('pages.view');
    app(AdminAccess::class)->authorizeScreen();
})->throwsNoExceptions();

it('refuses a user without the screen\'s permission with a 403 and the screen\'s message', function (): void {
    $this->actingAs(new GenericUser(['id' => 5]));

    expect(fn () => app(AdminAccess::class)->authorizeScreen('schema.manage', 'Not for you.'))
        ->toThrow(function (HttpException $e): void {
            expect($e->getStatusCode())->toBe(403)->and($e->getMessage())->toBe('Not for you.');
        });
});

it('refuses a user the app\'s admin gate turns away, whatever their permissions', function (): void {
    Gate::define(AdminServiceProvider::GATE, fn (): bool => false);
    $this->actingAs(new GenericUser(['id' => 1]));

    expect(fn () => app(AdminAccess::class)->authorizeScreen())->toThrow(AuthorizationException::class);
});

it('keeps superuser screens to superusers', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    app(AdminAccess::class)->authorizeSuperuser();

    $this->actingAs(new GenericUser(['id' => 5]));
    expect(fn () => app(AdminAccess::class)->authorizeSuperuser('Superusers only.'))
        ->toThrow(function (HttpException $e): void {
            expect($e->getStatusCode())->toBe(403);
        });
});
