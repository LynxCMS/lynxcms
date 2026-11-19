<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Closure;
use Illuminate\Http\Request;
use LynxCms\Admin\Auth\TwoFactorRequirement;
use Symfony\Component\HttpFoundation\Response;

/**
 * An admin user without two-factor authentication sets it up on Account security before any
 * other admin screen, when the admin requires it (ADR-0128). Runs after RequireOwnPassword, so a
 * temporary password is replaced first.
 *
 * @internal
 */
final class RequireTwoFactor
{
    public const string ROUTE = 'lynx.admin.account';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isExempt = $request->routeIs(self::ROUTE, RequireOwnPassword::ROUTE, RequireOwnPassword::ROUTE.'.save');

        if (! $isExempt && TwoFactorRequirement::isMissing()) {
            return redirect()->route(self::ROUTE);
        }

        return $next($request);
    }
}
