<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;
use LynxCms\Core\Schema\SchemaSync;

it('creates a superuser and an editor role on install, once', function (): void {
    installLynx();
    lynx()->run(InstallLynx::class);

    $editor = Role::query()->where('name', 'editor')->firstOrFail();
    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe(['editor', 'superuser'])
        ->and(Role::query()->where('name', 'superuser')->firstOrFail()->is_superuser)->toBeTrue()
        ->and($editor->grants()->pluck('permission')->sort()->values()->all())
        ->toBe(['admin.access', 'pages.create', 'pages.edit', 'pages.move', 'pages.publish', 'pages.status', 'pages.view'])
        ->and(RoleAssignment::query()->count())->toBe(0);
});

it('makes the given user a superuser on install', function (): void {
    $this->artisan('lynx:install', ['--user' => '42'])->assertSuccessful();

    expect(RoleAssignment::query()->where('user_id', '42')->exists())->toBeTrue();

    $this->artisan('lynx:install', ['--user' => '42'])->expectsOutputToContain('already installed')->assertSuccessful();
    expect(RoleAssignment::query()->where('user_id', '42')->count())->toBe(1);
});

it('exports roles with branches as paths and applies them on another machine', function (): void {
    courseSite();
    $dir = sys_get_temp_dir().'/lynx-roles-'.uniqid();
    lynx()->run(SaveRole::class, ['name' => 'course-editor', 'grants' => [['permission' => 'pages.edit', 'templates' => ['course'], 'branches' => [lynx()->pages->get('/courses/')->id]]]]);
    lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => ['course-editor']]);

    app(SchemaSync::class)->export($dir);
    $yaml = File::get("{$dir}/roles/course-editor.yaml");

    expect($yaml)->toContain('/courses/')->not->toContain('users')->not->toContain('user_id');

    Role::query()->where('name', 'course-editor')->delete();
    $result = app(SchemaSync::class)->apply($dir);
    $grant = Role::query()->where('name', 'course-editor')->firstOrFail()->grants()->firstOrFail();

    expect($result->hasErrors())->toBeFalse()
        ->and([$grant->templates, $grant->branches])->toBe([['course'], [lynx()->pages->get('/courses/')->id]])
        ->and(app(SchemaSync::class)->apply($dir)->isEmpty())->toBeTrue();

    File::deleteDirectory($dir);
});

it('deletes roles missing from the files only with force', function (): void {
    courseSite();
    $dir = sys_get_temp_dir().'/lynx-roles-'.uniqid();
    app(SchemaSync::class)->export($dir);
    lynx()->run(SaveRole::class, ['name' => 'temporary']);

    expect(app(SchemaSync::class)->apply($dir)->skipped)->toContain('Delete role temporary (not in the files; run with --force to delete)')
        ->and(Role::query()->where('name', 'temporary')->exists())->toBeTrue();

    app(SchemaSync::class)->apply($dir, force: true);
    expect(Role::query()->where('name', 'temporary')->exists())->toBeFalse();

    File::deleteDirectory($dir);
});
