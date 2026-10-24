<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Languages\SetCurrentLanguage;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Permissions\RoleAssignment;

/*
 * A user without a role has no rights, and a web request with nobody signed in is a visitor,
 * not the system (ADR-0078). The suite runs in the old open mode (tests/TestCase.php); these
 * tests turn it off, as a real site has it.
 */

beforeEach(function (): void {
    config(['lynx.permissions.open_until_roles' => false]);
    courseSite();
    createCourses('alpha');
});

it('gives a signed in user without a role no rights before anyone holds a role', function (): void {
    $authorizer = app(Authorizer::class);

    expect(RoleAssignment::query()->exists())->toBeFalse()
        ->and($authorizer->allows(Actor::user(7), 'admin.access'))->toBeFalse()
        ->and($authorizer->isSuperuser(Actor::user(7)))->toBeFalse()
        ->and($authorizer->allows(Actor::cli(), 'admin.access'))->toBeTrue();
});

it('stops a user without a role from making themselves a superuser', function (): void {
    lynx()->actingAs(Actor::user(7), fn (): ActionResult => lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => ['superuser']]));
})->throws(AuthorizationException::class, 'Only superusers may run [roles.assign].');

it('keeps a user without a role out of the admin and says how to make a superuser', function (): void {
    config(['app.debug' => true]);

    $this->actingAs(new GenericUser(['id' => 7]))->get('/lynx/pages')
        ->assertForbidden()
        ->assertSee('No one is a superuser yet. Run php artisan lynx:superuser to make one.');
});

it('gives no hint without debugging', function (): void {
    config(['app.debug' => false]);

    $this->actingAs(new GenericUser(['id' => 7]))->get('/lynx/pages')->assertForbidden()->assertDontSee('lynx:superuser');
});

it('gives no hint once someone is a superuser', function (): void {
    config(['app.debug' => true]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);

    $this->actingAs(new GenericUser(['id' => 7]))->get('/lynx/pages')->assertForbidden()->assertDontSee('lynx:superuser');
    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/pages')->assertOk();
});

it('shows a user without a role the live page, not the draft', function (): void {
    lynx()->pages->saveDraft('/courses/alpha/', ['title' => ['default' => 'Alpha in draft']]);

    $this->actingAs(new GenericUser(['id' => 7]))->get('/courses/alpha/?preview=draft')->assertOk()->assertDontSee('Alpha in draft');
});

it('refuses MCP clients and agents that act for no user without an anonymous role', function (): void {
    $authorizer = app(Authorizer::class);

    expect($authorizer->allows(Actor::mcp(), 'pages.view'))->toBeFalse()
        ->and($authorizer->allows(Actor::ai(), 'pages.view'))->toBeFalse();

    config(['lynx.permissions.anonymous_role' => 'editor']);
    $authorizer->flush();

    expect($authorizer->allows(Actor::mcp(), 'pages.view'))->toBeTrue();
});

it('runs actions for a visitor as a guest, allowed only what is open to everyone', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha/');
    Route::post('suggest', fn (): string => lynx()->run(SavePage::class, ['id' => $alpha?->id, 'values' => ['title' => ['default' => 'Hacked']]])->changes->summary()[0] ?? '')->middleware('web');
    Route::post('language', function (): string {
        lynx()->run(SetCurrentLanguage::class, ['language' => 'pt']);

        return app(ActionRunner::class)->actor()->kind;
    })->middleware('web');

    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->post('/suggest')->assertForbidden();
    $this->post('/language')->assertOk()->assertSee('guest');
    expect((string) lynx()->pages->get('/courses/alpha/')?->title)->toBe('Alpha');
});

it('lets code run as the system for a visitor when it asks to', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha/');
    Route::post('webhook', function () use ($alpha): string {
        lynx()->actingAs(Actor::system(), fn (): ActionResult => lynx()->run(SavePage::class, ['id' => $alpha?->id, 'values' => ['title' => ['default' => 'Booked']]]));

        return 'ok';
    })->middleware('web');

    $this->withoutMiddleware(ValidateCsrfToken::class)->post('/webhook')->assertOk();

    expect((string) lynx()->pages->get('/courses/alpha/')?->title)->toBe('Booked');
});

it('keeps the old open mode for signed in users, never for visitors, when asked', function (): void {
    config(['lynx.permissions.open_until_roles' => true]);
    $authorizer = app(Authorizer::class);
    $authorizer->flush();

    expect($authorizer->allows(Actor::user(7), 'schema.manage'))->toBeTrue()
        ->and($authorizer->allows(Actor::guest(), 'pages.view'))->toBeFalse();

    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);

    expect($authorizer->allows(Actor::user(7), 'schema.manage'))->toBeFalse();
});

it('warns on install when no one is a superuser, and not once someone is', function (): void {
    $this->artisan('lynx:install', ['--no-interaction' => true])->expectsOutputToContain('No one is a superuser yet')->assertSuccessful();
    $this->artisan('lynx:install', ['--user' => '1'])->doesntExpectOutputToContain('No one is a superuser yet')->assertSuccessful();
});
