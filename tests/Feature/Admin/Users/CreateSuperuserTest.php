<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Users\CreateUser;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Permissions\PermissionHolders;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;
use Workbench\App\Models\User;

/*
 * A new site gets its first superuser without an account made some other way first:
 * lynx:superuser creates one (or promotes an existing account), and lynx:install offers it.
 */

beforeEach(fn () => config(['lynx.permissions.open_until_roles' => false]));

function rolesOf(string $email): array
{
    $id = (string) User::query()->where('email', $email)->value('id');

    return Role::query()->whereKey(RoleAssignment::query()->where('user_id', $id)->pluck('role_id'))->pluck('name')->all();
}

it('creates an account with a hashed password and its roles', function (): void {
    installLynx();

    $id = lynx()->run(CreateUser::class, ['name' => 'Ada Lovelace', 'email' => 'Ada@Example.com', 'password' => 'a long password', 'roles' => ['superuser']])->value;
    $ada = User::query()->findOrFail($id);

    expect($ada->email)->toBe('ada@example.com')
        ->and(Hash::check('a long password', $ada->password))->toBeTrue()
        ->and(rolesOf('ada@example.com'))->toBe(['superuser']);
});

it('refuses an email that already has an account, and a weak password', function (): void {
    installLynx();
    User::query()->forceCreate(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x']);

    expect(fn (): ActionResult => lynx()->run(CreateUser::class, ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'a long password']))->toThrow(ValidationException::class, 'already has an account')
        ->and(fn (): ActionResult => lynx()->run(CreateUser::class, ['name' => 'Bo', 'email' => 'bo@example.com', 'password' => 'short']))->toThrow(ValidationException::class);
});

it('creates nothing on a dry run or when a hook halts it', function (): void {
    installLynx();
    lynx()->run(CreateUser::class, ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'a long password'], dryRun: true);
    expect(User::query()->count())->toBe(0);

    lynx()->hook(CreateUser::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): ActionResult => lynx()->run(CreateUser::class, ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'a long password']))->toThrow(ActionHalted::class)
        ->and(User::query()->count())->toBe(0);
});

it('lets only superusers create accounts, never agents', function (): void {
    installLynx();

    expect(fn (): mixed => lynx()->actingAs(Actor::user(9), fn (): ActionResult => lynx()->run(CreateUser::class, ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'a long password'])))
        ->toThrow(AuthorizationException::class)
        ->and(CreateUser::exposedAsTool())->toBeFalse();
});

it('creates the first superuser from the console, installing Lynx if needed', function (): void {
    $this->artisan('lynx:superuser')
        ->expectsQuestion('Email', 'ada@example.com')
        ->expectsQuestion('Name', 'Ada Lovelace')
        ->expectsQuestion('Password', 'a long password')
        ->expectsQuestion('Password again', 'a long password')
        ->expectsOutputToContain('Ada Lovelace <ada@example.com> is a superuser')
        ->assertSuccessful();

    expect(rolesOf('ada@example.com'))->toBe(['superuser'])
        ->and(app(PermissionHolders::class)->hasSuperuser())->toBeTrue()
        ->and(lynx()->pages->get('/'))->not->toBeNull();
});

it('asks again when the passwords differ', function (): void {
    $this->artisan('lynx:superuser')
        ->expectsQuestion('Email', 'ada@example.com')
        ->expectsQuestion('Name', 'Ada')
        ->expectsQuestion('Password', 'a long password')
        ->expectsQuestion('Password again', 'another password')
        ->expectsOutputToContain('The passwords differ')
        ->expectsQuestion('Password', 'a long password')
        ->expectsQuestion('Password again', 'a long password')
        ->assertSuccessful();
});

it('makes an existing account a superuser, keeping its roles', function (): void {
    installLynx();
    $bo = User::query()->forceCreate(['name' => 'Bo', 'email' => 'bo@example.com', 'password' => 'x']);
    Role::query()->where('name', 'editor')->firstOrFail()->assign($bo->id);

    $this->artisan('lynx:superuser', ['--email' => 'BO@example.com'])
        ->expectsConfirmation('Bo <bo@example.com> already has an account. Make them a superuser?', 'yes')
        ->assertSuccessful();

    expect(rolesOf('bo@example.com'))->toEqualCanonicalizing(['editor', 'superuser']);
});

it('runs without questions for deploy scripts', function (): void {
    $this->artisan('lynx:superuser', ['--email' => 'ada@example.com', '--name' => 'Ada', '--password' => 'a long password', '--no-interaction' => true])->assertSuccessful();
    expect(rolesOf('ada@example.com'))->toBe(['superuser']);

    $this->artisan('lynx:superuser', ['--email' => 'cy@example.com', '--name' => 'Cy', '--no-interaction' => true])
        ->expectsOutputToContain('Give --password')
        ->assertFailed();
});

it('offers to create the superuser when installing a site without one', function (): void {
    $this->artisan('lynx:install')
        ->expectsConfirmation('No one is a superuser yet. Create one now?', 'yes')
        ->expectsQuestion('Email', 'ada@example.com')
        ->expectsQuestion('Name', 'Ada')
        ->expectsQuestion('Password', 'a long password')
        ->expectsQuestion('Password again', 'a long password')
        ->assertSuccessful();

    expect(rolesOf('ada@example.com'))->toBe(['superuser']);
});

it('makes an existing account a superuser on install, by email', function (): void {
    User::query()->forceCreate(['name' => 'Bo', 'email' => 'bo@example.com', 'password' => 'x']);

    $this->artisan('lynx:install', ['--user' => 'bo@example.com'])->assertSuccessful();

    expect(rolesOf('bo@example.com'))->toBe(['superuser']);
    $this->artisan('lynx:install', ['--user' => 'nobody@example.com'])->expectsOutputToContain('No user has the email [nobody@example.com]')->assertFailed();
});
