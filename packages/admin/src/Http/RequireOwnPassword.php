<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Fortify;
use LynxCms\Admin\Auth\TemporaryPassword;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone signed in with a temporary password chooses their own before any other admin screen
 * (ADR-0126). One that expired signs them out, however they signed in: an app's own sign-in may
 * not fire the event RefuseExpiredTemporaryPassword listens to (ADR-0129).
 *
 * @internal
 */
final class RequireOwnPassword
{
    public const string ROUTE = 'lynx.admin.account.new-password';

    private const string SIGN_IN_ROUTE = 'login';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->user()?->getAuthIdentifier();
        $temporary = $id === null ? null : TemporaryPassword::query()->where('user_id', Value::string($id))->first();

        if ($temporary?->hasExpired() === true) {
            return $this->signOut($request);
        }

        if ($temporary !== null && ! $request->routeIs(self::ROUTE, self::ROUTE.'.save')) {
            return redirect()->route(self::ROUTE);
        }

        return $next($request);
    }

    private function signOut(Request $request): Response
    {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $signIn = Route::has(self::SIGN_IN_ROUTE) ? route(self::SIGN_IN_ROUTE) : '/';

        return redirect($signIn)->withErrors([Fortify::username() => __('lynx-admin::auth.temporary.expired')]);
    }
}
