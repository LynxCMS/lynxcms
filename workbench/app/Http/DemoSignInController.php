<?php

declare(strict_types=1);

namespace Workbench\App\Http;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Workbench\Database\Seeders\DemoUsersSeeder;

/**
 * Switches between the demo users of the workbench, which has no login form. Signed out, the
 * admin signs in the demo superuser (SignInDemoUser).
 */
final class DemoSignInController
{
    private const string ADMIN_HOME = '/lynx/pages';

    public function signIn(Request $request, string $user): RedirectResponse
    {
        $email = DemoUsersSeeder::USERS[$user][0] ?? abort(404);
        $user = User::query()->where('email', $email)->first() ?? abort(404, 'Run composer build to seed the demo users.');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(self::ADMIN_HOME);
    }

    public function signOut(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();

        // To the site as a visitor: opening the admin signs the demo superuser in again.
        return redirect('/');
    }
}
