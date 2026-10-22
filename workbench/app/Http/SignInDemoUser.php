<?php

declare(strict_types=1);

namespace Workbench\App\Http;

use Closure;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Workbench\Database\Seeders\DemoUsersSeeder;

/**
 * The workbench admin always runs as a demo user, never as an anonymous "system": when nobody is
 * signed in, it signs in the demo superuser. Roles, edit locks and draft previews then behave as
 * on a real site. /demo/sign-in/{key} switches users; /demo/sign-out signs out (the sign-in screen is /lynx/login).
 */
final class SignInDemoUser
{
    private const string DEFAULT_USER = 'admin';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guest()) {
            $user = User::query()->where('email', DemoUsersSeeder::USERS[self::DEFAULT_USER][0])->first();

            if ($user !== null) {
                Auth::login($user);
                $request->session()->regenerate();
            }
        }

        return $next($request);
    }
}
