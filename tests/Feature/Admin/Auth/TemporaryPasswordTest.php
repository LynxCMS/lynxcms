<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Auth\CreateUser;
use LynxCms\Admin\Auth\Invitation;
use LynxCms\Admin\Auth\InviteUser;
use LynxCms\Admin\Auth\SetTemporaryPassword;
use LynxCms\Admin\Auth\TemporaryPassword;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Permissions\RoleAssignment;
use Workbench\App\Models\User;

/*
 * Temporary passwords (ADR-0126): a superuser creates a user, or helps one who forgot their
 * password, without sending email. The password is shown once, works for a few days, and the
 * user must choose their own before anything else in the admin.
 */

beforeEach(function (): void {
    courseSite();
    Notification::fake();
    $this->admin = User::query()->forceCreate(['name' => 'Ada Admin', 'email' => 'ada@example.com', 'password' => 'secret password']);
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => (string) $this->admin->id, 'roles' => ['superuser']]);
    $this->actingAs($this->admin);
});

/** @return array{user: string, password: string, expires_at: string} */
function createWithPassword(array $input = []): array
{
    return lynx()->run(CreateUser::class, $input + ['name' => 'Bruno Costa', 'email' => 'bruno@example.com', 'roles' => ['editor']])->value;
}

function signIn(string $email, string $password): TestResponse
{
    Auth::logout();

    return test()->post('/lynx/login', ['email' => $email, 'password' => $password]);
}

it('creates the user with their roles and a strong password, and sends no email', function (): void {
    $created = createWithPassword();

    expect($created['password'])->toHaveLength(20)->toMatch('/[a-z]/')->toMatch('/[A-Z]/')->toMatch('/\d/')
        ->and(RoleAssignment::query()->where('user_id', $created['user'])->count())->toBe(1)
        ->and(Invitation::query()->where('user_id', $created['user'])->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('keeps the password out of the activity log', function (): void {
    $created = createWithPassword();

    expect(ActivityEvent::query()->where('action', 'users.create')->exists())->toBeTrue()
        ->and(ActivityEvent::query()->get()->toJson())->not->toContain($created['password']);
});

it('signs the user in with it and asks for their own password first', function (): void {
    $created = createWithPassword();

    signIn('bruno@example.com', $created['password'])->assertRedirect('/lynx/pages');
    $this->get('/lynx/pages')->assertRedirect('/lynx/account/new-password');
    $this->get('/lynx/account/new-password')->assertOk()->assertSee('Choose your password');
});

it('lets the user choose their own password, and then opens the admin', function (): void {
    $created = createWithPassword();
    signIn('bruno@example.com', $created['password']);

    $this->post('/lynx/account/new-password', ['password' => 'a long new password', 'password_confirmation' => 'a long new password'])
        ->assertRedirect('/lynx/pages');

    expect(Hash::check('a long new password', (string) User::query()->where('email', 'bruno@example.com')->value('password')))->toBeTrue()
        ->and(TemporaryPassword::query()->where('user_id', $created['user'])->exists())->toBeFalse();
    $this->get('/lynx/pages')->assertOk();
});

it('refuses the temporary password as the new one', function (): void {
    $created = createWithPassword();
    signIn('bruno@example.com', $created['password']);

    $this->post('/lynx/account/new-password', ['password' => $created['password'], 'password_confirmation' => $created['password']])
        ->assertSessionHasErrors('password');
});

it('stops working after a few days', function (): void {
    $created = createWithPassword();
    $this->travel(4)->days();

    signIn('bruno@example.com', $created['password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('gives an existing user a new temporary password, replacing theirs', function (): void {
    $bruno = User::query()->forceCreate(['name' => 'Bruno Costa', 'email' => 'bruno@example.com', 'password' => 'forgotten password']);
    lynx()->run(AssignRoles::class, ['user' => (string) $bruno->id, 'roles' => ['editor']]);

    $given = lynx()->run(SetTemporaryPassword::class, ['user' => (string) $bruno->id])->value;

    signIn('bruno@example.com', 'forgotten password')->assertSessionHasErrors('email');
    signIn('bruno@example.com', $given['password'])->assertRedirect('/lynx/pages');
    $this->get('/lynx/pages')->assertRedirect('/lynx/account/new-password');
});

it('ends a pending invitation when it gives a temporary password', function (): void {
    lynx()->run(InviteUser::class, ['name' => 'Bruno Costa', 'email' => 'bruno@example.com']);
    $bruno = User::query()->where('email', 'bruno@example.com')->firstOrFail();

    lynx()->run(SetTemporaryPassword::class, ['user' => (string) $bruno->id]);

    expect(Invitation::query()->where('user_id', (string) $bruno->id)->exists())->toBeFalse();
});

it('gives no temporary password to the superuser asking for it', function (): void {
    lynx()->run(SetTemporaryPassword::class, ['user' => (string) $this->admin->id]);
})->throws(ValidationException::class);

it('refuses an email that already has an account', function (): void {
    createWithPassword(['email' => 'ada@example.com']);
})->throws(ValidationException::class);

it('lets only superusers create users or give temporary passwords', function (string $action, array $input): void {
    $eva = User::query()->forceCreate(['name' => 'Eva', 'email' => 'eva@example.com', 'password' => 'secret password']);
    lynx()->run(AssignRoles::class, ['user' => (string) $eva->id, 'roles' => ['editor']]);
    $this->actingAs($eva);

    lynx()->run($action, $input === [] ? ['user' => (string) $this->admin->id] : $input);
})->with([
    'create' => [CreateUser::class, ['name' => 'Bruno Costa', 'email' => 'bruno@example.com']],
    'temporary password' => [SetTemporaryPassword::class, []],
])->throws(AuthorizationException::class);

it('is not an agent tool', function (): void {
    expect(CreateUser::exposedAsTool())->toBeFalse()->and(SetTemporaryPassword::exposedAsTool())->toBeFalse()
        ->and(app(ActionRegistry::class)->find('users.create'))->toBe(CreateUser::class)
        ->and(app(ActionRegistry::class)->find('users.temporary_password'))->toBe(SetTemporaryPassword::class);
});
