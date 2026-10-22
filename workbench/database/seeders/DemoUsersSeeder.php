<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

/**
 * Users with different roles, to check by hand what each one sees in the admin (ADR-0033).
 * Switch between them at /demo/sign-in/{key}, or sign in at /lynx/login with their email and the
 * password "password".
 */
final class DemoUsersSeeder extends Seeder
{
    /** Sign-in key => [email, name, role]. */
    public const array USERS = [
        'admin' => ['admin@example.com', 'Ada Admin', 'superuser'],
        'editor' => ['editor@example.com', 'Eva Editor', 'editor'],
        'courses' => ['courses@example.com', 'Carl Courses', 'course-editor'],
    ];

    /** Workbench only: the demo is local and its users are public. */
    private const string PASSWORD = 'password';

    /** Everyday page work, limited to the courses branch. */
    private const array COURSE_EDITOR_PAGE_PERMISSIONS = ['pages.view', 'pages.edit', 'pages.create', 'pages.status', 'pages.move', 'pages.publish'];

    public function run(): void
    {
        $this->courseEditorRole();

        foreach (self::USERS as [$email, $name, $role]) {
            lynx()->run(AssignRoles::class, ['user' => $this->user($email, $name), 'roles' => [$role]]);
        }
    }

    private function courseEditorRole(): void
    {
        $courses = lynx()->pages->get('/courses/')?->id;
        $grants = array_map(
            fn (string $permission): array => ['permission' => $permission, 'branches' => $courses === null ? [] : [$courses]],
            self::COURSE_EDITOR_PAGE_PERMISSIONS,
        );

        lynx()->run(SaveRole::class, ['name' => 'course-editor', 'label' => 'Course editor', 'grants' => [['permission' => 'admin.access'], ...$grants]]);
    }

    /** The user's id, creating the user the first time. */
    private function user(string $email, string $name): string
    {
        $user = User::query()->where('email', $email)->first()
            ?? User::query()->forceCreate(['email' => $email, 'name' => $name, 'password' => Hash::make(self::PASSWORD)]);

        return (string) $user->getKey();
    }
}
