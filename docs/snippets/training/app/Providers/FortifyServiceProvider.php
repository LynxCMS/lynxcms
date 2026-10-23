<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

// #region provider
/**
 * Fortify's screens in the site's design (chapter 8): sign in, sign up, reset a password, and the
 * second factor for staff who turned it on.
 */
final class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn (): Factory|View => view('auth.login'));
        Fortify::registerView(fn (): Factory|View => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn (): Factory|View => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request): Factory|View => view('auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn (): Factory|View => view('auth.two-factor-challenge'));

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->session()->get('login.id')));
    }
}
// #endregion provider
