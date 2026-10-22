<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use LynxCms\Admin\Admin;
use LynxCms\Admin\Http\InvitationController;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Support\Value;

/**
 * The admin's own sign-in screens (ADR-0041): Laravel Fortify under the admin prefix (/lynx/login),
 * without registration (users are invited), with two-factor authentication, passkeys and Lynx's views. On unless the app configures Fortify
 * itself (config/fortify.php), when its own screens sign users in.
 *
 * @internal
 */
final class AdminAuth
{
    private const int LOGIN_ATTEMPTS_PER_MINUTE = 5;

    private const int PASSKEY_ATTEMPTS_PER_MINUTE = 10;

    public static function isEnabled(): bool
    {
        $setting = config('lynx-admin.auth.enabled', 'auto');

        return $setting === 'auto' ? ! self::appConfiguresFortify() : Value::bool($setting);
    }

    /** On register: Fortify reads this config when it registers its routes. */
    public static function configure(): void
    {
        if (self::appConfiguresFortify()) {
            return;
        }

        if (! self::isEnabled()) {
            // Fortify's defaults would offer registration at /register: no routes unless asked.
            Fortify::ignoreRoutes();

            return;
        }

        $prefix = trim(Value::string(config('lynx-admin.prefix')), '/');
        config([
            'fortify.prefix' => $prefix,
            'fortify.home' => "/{$prefix}/pages",
            'fortify.views' => true,
            'fortify.lowercase_usernames' => true,
            'fortify.limiters.login' => 'lynx-login',
            'fortify.limiters.two-factor' => 'lynx-two-factor',
            'fortify.limiters.passkeys' => 'lynx-passkeys',
            'fortify.redirects.logout' => "/{$prefix}/login",
            'fortify.features' => [
                Features::resetPasswords(),
                Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
                Features::passkeys(['confirmPassword' => true]),
            ],
            // Fortify copies these when it registers, which may be before Lynx does.
            'passkeys.redirect' => "/{$prefix}/pages",
            'passkeys.throttle' => 'throttle:lynx-passkeys',
        ]);
        Invitations::configureBroker();
    }

    /** On boot: Lynx's views, password rules and rate limits. */
    public static function boot(): void
    {
        if (self::appConfiguresFortify() || ! self::isEnabled()) {
            return;
        }

        Fortify::loginView(fn (): mixed => view('lynx-admin::auth.login'));
        Fortify::requestPasswordResetLinkView(fn (): mixed => view('lynx-admin::auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request): mixed => view('lynx-admin::auth.reset-password', ['request' => $request]));
        Fortify::confirmPasswordView(fn (): mixed => view('lynx-admin::auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn (): mixed => view('lynx-admin::auth.two-factor-challenge'));
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Event::listen(UserInvited::class, SendInvitation::class);
        Event::listen(PasswordReset::class, ClearInvitation::class);
        app(ActionRegistry::class)->register(InviteUser::class, ResendInvitation::class);
        self::routes();

        RateLimiter::for('lynx-login', fn (Request $request): Limit => Limit::perMinute(self::LOGIN_ATTEMPTS_PER_MINUTE)
            ->by(Str::transliterate(Str::lower(Value::string($request->input(Fortify::username())))).'|'.$request->ip()));
        RateLimiter::for('lynx-two-factor', fn (Request $request): Limit => Limit::perMinute(self::LOGIN_ATTEMPTS_PER_MINUTE)
            ->by(Value::string($request->session()->get('login.id'))));
        RateLimiter::for('lynx-passkeys', fn (Request $request): Limit => Limit::perMinute(self::PASSKEY_ATTEMPTS_PER_MINUTE)
            ->by(Value::string($request->input('credential.id'), $request->session()->getId()).'|'.$request->ip()));
    }

    /** Accepting an invitation: guests only, throttled like the sign-in form. */
    private static function routes(): void
    {
        Admin::routes(function (): void {
            Route::view('account/security', 'lynx-admin::account.security')->middleware('password.confirm')->name('account');
        });
        Route::middleware(['web', 'guest'])->prefix(Value::string(config('lynx-admin.prefix')))->name('lynx.admin.')->group(function (): void {
            Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation');
            Route::post('invitation', [InvitationController::class, 'store'])->middleware('throttle:'.self::LOGIN_ATTEMPTS_PER_MINUTE.',1')->name('invitation.accept');
        });
    }

    private static function appConfiguresFortify(): bool
    {
        return file_exists(config_path('fortify.php'));
    }
}
