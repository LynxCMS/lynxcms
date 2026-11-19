<?php

declare(strict_types=1);

use LynxCms\Core\Lynx;
use Illuminate\Support\Facades\Artisan;
use LynxCms\Core\Actions\Roles\SaveRole;

/*
 * lynx:doctor checks the role people get when they sign up (ADR-0127): it must exist, and it
 * must never open the admin, or anyone who signs up could edit the site.
 */

beforeEach(fn (): Lynx => installLynx());

/** @return list<array{severity: string, message: string, fix: string|null}> */
function registrationRoleFindings(): array
{
    Artisan::call('lynx:doctor', ['--json' => true]);

    return collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->firstWhere('key', 'registration-role')['findings'];
}

it('passes when sign-ups get no role', function (): void {
    expect(registrationRoleFindings())->toBe([]);
});

it('passes when the role only reaches the website', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'member', 'grants' => [['permission' => 'pages.view']]]);
    config(['lynx.permissions.registration_role' => 'member']);

    expect(registrationRoleFindings())->toBe([]);
});

it('fails when the role opens the admin', function (array $role): void {
    lynx()->run(SaveRole::class, ['name' => 'member'] + $role);
    config(['lynx.permissions.registration_role' => 'member']);

    expect(array_column(registrationRoleFindings(), 'severity'))->toBe(['error']);
})->with([
    'admin.access' => [['grants' => [['permission' => 'admin.access']]]],
    'superuser' => [['superuser' => true]],
]);

it('warns when the role does not exist', function (): void {
    config(['lynx.permissions.registration_role' => 'member']);

    expect(array_column(registrationRoleFindings(), 'severity'))->toBe(['warning']);
});
