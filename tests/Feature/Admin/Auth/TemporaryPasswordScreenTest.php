<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use LynxCms\Admin\Auth\SetTemporaryPassword;
use LynxCms\Admin\Auth\TemporaryPassword;
use LynxCms\Admin\Livewire\UserRoles;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use Workbench\App\Models\User;

/*
 * Temporary passwords on Setup › Users (ADR-0126): adding a user with one instead of an
 * invitation, giving one to someone who forgot theirs, and a site that sends no email.
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

it('adds a user with a temporary password and shows it once', function (): void {
    $screen = Livewire::test(UserRoles::class)
        ->set('invite.name', 'Bruno Costa')->set('invite.email', 'bruno@example.com')->set('invite.roles', ['editor'])
        ->set('invite.method', 'password')
        ->call('addUser')
        ->assertHasNoErrors()
        ->assertSee('User Bruno Costa created. Temporary password:')
        ->assertSet('invite.email', '');

    $password = $screen->get('issued')['password'];
    expect($password)->toHaveLength(20);
    $screen->assertSee('Copy is not available without HTTPS. Select the password to copy it.');
    $screen->assertSee($password)->call('dismissIssued')->assertSet('issued', null)->assertDontSee($password);
    Notification::assertNothingSent();
});

it('still sends an invitation when that is the choice', function (): void {
    Livewire::test(UserRoles::class)
        ->set('invite.name', 'Bruno Costa')->set('invite.email', 'bruno@example.com')
        ->call('addUser')
        ->assertSet('status', 'Invitation sent to bruno@example.com.')
        ->assertSet('issued', null);
});

it('gives a listed user a new temporary password, and marks them', function (): void {
    $bruno = User::query()->forceCreate(['name' => 'Bruno Costa', 'email' => 'bruno@example.com', 'password' => 'forgotten password']);

    $screen = Livewire::test(UserRoles::class)
        ->assertSee('New temporary password')
        ->call('giveTemporaryPassword', (string) $bruno->id)
        ->assertSee('New temporary password for Bruno Costa:')
        ->assertSee('Temporary password');

    expect(TemporaryPassword::query()->where('user_id', (string) $bruno->id)->exists())->toBeTrue()
        ->and($screen->get('issued')['password'])->toHaveLength(20);
});

it('shows the error when the user is the superuser themselves', function (): void {
    Livewire::test(UserRoles::class)
        ->call('giveTemporaryPassword', (string) $this->admin->id)
        ->assertHasErrors('user')
        ->assertSet('issued', null);
});

it('offers only temporary passwords on a site that sends no email', function (): void {
    config(['lynx-admin.auth.mail' => false]);

    Livewire::test(UserRoles::class)
        ->assertSet('invite.method', 'password')
        ->assertSee('Create user')
        ->assertDontSee('Send an invitation by email')
        ->set('invite.name', 'Bruno Costa')->set('invite.email', 'bruno@example.com')
        ->set('invite.method', 'email')
        ->call('addUser')
        ->assertSee('User Bruno Costa created. Temporary password:');

    Notification::assertNothingSent();
});

it('never shows the password on a user\'s row or in a later render', function (): void {
    $bruno = User::query()->forceCreate(['name' => 'Bruno Costa', 'email' => 'bruno@example.com', 'password' => 'forgotten password']);
    $given = lynx()->run(SetTemporaryPassword::class, ['user' => (string) $bruno->id])->value;

    Livewire::test(UserRoles::class)->assertSee('Temporary password')->assertDontSee($given['password']);
});
