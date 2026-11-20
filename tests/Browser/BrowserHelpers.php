<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;

/*
 * Helpers for the browser tests of the sign-in flows (tests/Browser). Loaded by tests/Pest.php.
 */

const BROWSER_PASSWORD = 'Correct-Horse-Battery-9';

/** The demo site with a superuser and an editor role, as the sign-in tests need them. */
function signInSite(): void
{
    courseSite();
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.edit']]]);
}

/** A user with a role, who signs in with BROWSER_PASSWORD. */
function browserUser(string $email, string $role = 'superuser'): User
{
    $user = User::query()->forceCreate(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make(BROWSER_PASSWORD)]);
    lynx()->actingAs(Actor::system(), fn (): ActionResult => lynx()->run(AssignRoles::class, ['user' => (string) $user->id, 'roles' => [$role]]));

    return $user;
}

/** Turns two-factor authentication on for a user, as if they had scanned the code. */
function withTwoFactor(User $user): User
{
    app(EnableTwoFactorAuthentication::class)($user);
    $user->refresh();
    app(ConfirmTwoFactorAuthentication::class)($user, currentCode($user));
    // Fortify refuses a code used before; a person signs in long after setting up, with a new one.
    Cache::flush();

    return $user->refresh();
}

/** The code an authenticator app shows now for the user's secret. */
function currentCode(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp(decrypt((string) $user->refresh()->two_factor_secret));
}
