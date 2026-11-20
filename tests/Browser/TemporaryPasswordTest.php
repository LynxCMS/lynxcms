<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use LynxCms\Admin\Auth\CreateUserWithTemporaryPassword;
use LynxCms\Admin\Auth\TemporaryPassword;
use LynxCms\Contracts\Actions\Actor;
use Workbench\App\Models\User;

/*
 * A temporary password from a superuser (ADR-0126): it signs the user in once, and they set
 * their own password before any admin screen opens.
 */

const NEW_PASSWORD = 'My-Own-Password-2026';

beforeEach(function (): void {
    signInSite();
    $admin = browserUser('ada@example.com');
    $this->temporary = lynx()->actingAs(Actor::user($admin->id), fn (): string => lynx()->run(CreateUserWithTemporaryPassword::class, [
        'name' => 'Bruno Costa', 'email' => 'bruno@example.com', 'roles' => ['editor'],
    ])->value['password']);
});

it('signs in with the temporary password and asks for a new one before the admin', function (): void {
    visit('/lynx/login')
        ->fill('email', 'bruno@example.com')
        ->fill('password', $this->temporary)
        ->click('button[type=submit]')
        ->assertPathIs('/lynx/account/new-password')
        ->assertSee('You signed in with a temporary password.')
        ->fill('password', NEW_PASSWORD)
        ->fill('password_confirmation', NEW_PASSWORD)
        ->press('Save password')
        ->assertPathIsNot('/lynx/account/new-password')
        ->assertSee('Sign out')
        ->assertNoJavaScriptErrors();

    $bruno = User::query()->where('email', 'bruno@example.com')->firstOrFail();
    expect(Hash::check(NEW_PASSWORD, (string) $bruno->password))->toBeTrue()
        ->and(TemporaryPassword::query()->where('user_id', $bruno->id)->exists())->toBeFalse();
});

it('keeps asking for a new password when the user tries another screen first', function (): void {
    visit('/lynx/login')
        ->fill('email', 'bruno@example.com')
        ->fill('password', $this->temporary)
        ->click('button[type=submit]')
        ->navigate('/lynx/pages')
        ->assertPathIs('/lynx/account/new-password');
});
