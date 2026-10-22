<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\RoleForm;
use LynxCms\Admin\Livewire\RoleList;
use LynxCms\Admin\Livewire\UserRoles;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Permissions\Role;

beforeEach(function (): void {
    courseSite();
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => 'x'],
        ['id' => 2, 'name' => 'Bruno Costa', 'email' => 'bruno@example.com', 'password' => 'x'],
    ]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

function withRoles(): void
{
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['writer']]);
}

it('lists roles with their grants and users', function (): void {
    withRoles();

    $this->get('/lynx/roles')->assertOk()->assertSeeLivewire(RoleList::class);
    Livewire::test(RoleList::class)->assertViewHas('rows', fn (array $rows): bool => $rows['writer'] === ['label' => 'Writer', 'superuser' => false, 'grants' => 2, 'users' => 1]);
});

it('lets only superusers manage roles and users', function (): void {
    withRoles();

    $this->actingAs(new GenericUser(['id' => 2]))->get('/lynx/roles')->assertForbidden();
    $this->actingAs(new GenericUser(['id' => 2]))->get('/lynx/users')->assertForbidden();
    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/users')->assertOk();
});

it('creates a role with grants limited to templates and branches', function (): void {
    $courses = lynx()->pages->get('/courses/')->id;

    Livewire::test(RoleForm::class)
        ->set('name', 'course-editor')
        ->set('grants.admin__access.on', true)
        ->set('grants.pages__edit.on', true)
        ->set('grants.pages__edit.templates', ['course'])
        ->set('grants.pages__edit.branches', [$courses])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('lynx.admin.roles.edit', 'course-editor'));

    $grant = Role::query()->where('name', 'course-editor')->firstOrFail()->grants()->where('permission', 'pages.edit')->firstOrFail();
    expect([$grant->templates, $grant->branches])->toBe([['course'], [$courses]]);
});

it('edits a role\'s grants', function (): void {
    withRoles();

    Livewire::test(RoleForm::class, ['role' => 'writer'])
        ->assertSet('grants.pages__edit.on', true)
        ->set('grants.pages__edit.on', false)
        ->set('grants.pages__publish.on', true)
        ->call('save')->assertHasNoErrors()->assertDispatched('lynx-notify', message: 'Saved.');

    expect(Role::query()->where('name', 'writer')->firstOrFail()->grants()->pluck('permission')->all())->toBe(['admin.access', 'pages.publish']);
});

it('deletes a role after confirmation', function (): void {
    withRoles();

    Livewire::test(RoleForm::class, ['role' => 'writer'])->call('confirmDelete')->assertSee('1 user loses it')->call('delete')->assertRedirect(route('lynx.admin.roles'));

    expect(Role::query()->where('name', 'writer')->exists())->toBeFalse();
});

it('finds users and sets their roles', function (): void {
    withRoles();

    Livewire::test(UserRoles::class)
        ->assertSee(['Ana Lima', 'Bruno Costa'])
        ->set('search', 'bru')->assertSee('Bruno Costa')->assertDontSee('Ana Lima')
        ->set('roles.2', ['writer', 'superuser'])
        ->call('saveRoles', '2')
        ->assertHasNoErrors()
        ->assertDispatched('lynx-notify', message: 'Saved.');

    expect(Role::query()->where('name', 'superuser')->firstOrFail()->assignments()->count())->toBe(2);
});

it('explains why the last superuser keeps the role', function (): void {
    withRoles();

    Livewire::test(UserRoles::class)->set('roles.1', [])->call('saveRoles', '1')->assertHasErrors('roles')->assertSee('last superuser');
});

it('explains that managing menus needs viewing pages', function (): void {
    withRoles();

    Livewire::test(RoleForm::class)
        ->set('name', 'menus-only')
        ->set('grants.menus__manage.on', true)
        ->call('save')
        ->assertHasErrors('grants')
        ->assertSee('[menus.manage] needs [pages.view] in the same role.');
});
