<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;
use Workbench\App\Http\SignInDemoUser;
use Workbench\Database\Seeders\DemoUsersSeeder;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    $this->seed(DemoUsersSeeder::class);
});

function demoUserId(string $email): string
{
    return (string) DB::table('users')->where('email', $email)->value('id');
}

it('seeds a superuser, an editor and an editor limited to the courses', function (): void {
    $rolesOf = fn (string $email): array => Role::query()
        ->whereKey(RoleAssignment::query()->where('user_id', demoUserId($email))->pluck('role_id'))
        ->pluck('name')->all();

    expect($rolesOf('admin@example.com'))->toBe(['superuser'])
        ->and($rolesOf('editor@example.com'))->toBe(['editor'])
        ->and($rolesOf('courses@example.com'))->toBe(['course-editor']);
});

it('seeds them once', function (): void {
    $this->seed(DemoUsersSeeder::class);

    expect(DB::table('users')->count())->toBe(3)
        ->and(RoleAssignment::query()->count())->toBe(3);
});

it('signs in as a demo user and out again, for checking what each one sees', function (): void {
    $this->get('/demo/sign-in/courses')->assertRedirect('/lynx/pages');

    expect((string) Auth::id())->toBe(demoUserId('courses@example.com'))
        ->and(app(AdminAccess::class)->onPage(lynx()->pages->get('/courses/alpha/'))['edit'])->toBeTrue()
        ->and(app(AdminAccess::class)->onPage(lynx()->pages->get('/'))['edit'])->toBeFalse();

    $this->get('/demo/sign-out')->assertRedirect('/');
    expect(Auth::check())->toBeFalse();
});

it('knows only the demo users', function (): void {
    $this->get('/demo/sign-in/nobody')->assertNotFound();
});

it('signs the demo superuser in for the admin when nobody is signed in', function (): void {
    $request = Request::create('/lynx/pages');
    $request->setLaravelSession(app('session.store'));

    (new SignInDemoUser)->handle($request, fn (): ResponseFactory|Response => response('ok'));

    expect((string) Auth::id())->toBe(demoUserId('admin@example.com'));
});

it('leaves a signed in demo user as they are', function (): void {
    $this->get('/demo/sign-in/courses');
    $request = Request::create('/lynx/pages');
    $request->setLaravelSession(app('session.store'));

    (new SignInDemoUser)->handle($request, fn (): ResponseFactory|Response => response('ok'));

    expect((string) Auth::id())->toBe(demoUserId('courses@example.com'));
});
