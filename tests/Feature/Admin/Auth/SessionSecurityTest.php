<?php

declare(strict_types=1);

use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use LynxCms\Admin\Auth\SetTemporaryPassword;
use LynxCms\Admin\Auth\TemporaryPassword;
use LynxCms\Admin\Http\RequireOwnPassword;
use LynxCms\Admin\Http\RequireTwoFactor;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use Workbench\App\Models\User;

/*
 * Sessions after a password changes (ADR-0129): Laravel's auth.session signs out every other
 * session and remember-me cookie of the user, admin components run the admin's own checks on
 * every update, and sign-in attempts are limited per account too.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.edit']]]);
    $this->bruno = User::query()->forceCreate(['name' => 'Bruno', 'email' => 'bruno@example.com', 'password' => Hash::make('correct horse battery')]);
    lynx()->actingAs(Actor::system(), fn (): ActionResult => lynx()->run(AssignRoles::class, ['user' => (string) $this->bruno->id, 'roles' => ['editor']]));
});

function signInAsBruno(string $password = 'correct horse battery', string $ip = '127.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->post('/lynx/login', ['email' => 'bruno@example.com', 'password' => $password]);
}

/** The guard keeps the user it loaded between requests of one test; a new request loads it again. */
function reloadSignedInUser(): void
{
    Auth::forgetGuards();
}

it('signs a session out when a superuser gives its user a temporary password', function (): void {
    signInAsBruno()->assertRedirect('/lynx/pages');
    $this->get('/lynx/pages')->assertOk();

    lynx()->actingAs(Actor::system(), fn (): ActionResult => lynx()->run(SetTemporaryPassword::class, ['user' => (string) $this->bruno->id]));
    reloadSignedInUser();

    $this->get('/lynx/pages')->assertRedirect('/lynx/login');
    $this->assertGuest();
});

it('signs a session out when its password changes anywhere else', function (): void {
    signInAsBruno();

    $this->bruno->forceFill(['password' => Hash::make('a brand new password')])->save();
    reloadSignedInUser();

    $this->get('/lynx/pages')->assertRedirect('/lynx/login');
});

it('runs the admin\'s own checks on every admin component update', function (): void {
    expect(Livewire::getPersistentMiddleware())->toContain(AuthenticateSession::class, RequireOwnPassword::class, RequireTwoFactor::class);
});

it('signs out a user whose temporary password expired, however they signed in', function (): void {
    TemporaryPassword::query()->create(['user_id' => (string) $this->bruno->id, 'expires_at' => now()->subMinute()]);
    $this->actingAs($this->bruno);

    $this->get('/lynx/pages')->assertRedirect('/lynx/login');
    $this->assertGuest();
});

it('limits sign-in attempts per account, from any address', function (): void {
    foreach (range(1, 30) as $attempt) {
        signInAsBruno('wrong password', "10.0.0.{$attempt}");
    }

    signInAsBruno(ip: '10.0.1.1')->assertTooManyRequests();
    Auth::logout();
});
