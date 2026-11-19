<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Closure;
use Illuminate\Http\Request;
use LynxCms\Admin\Auth\TemporaryPassword;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone signed in with a temporary password chooses their own before any other admin screen
 * (ADR-0126).
 *
 * @internal
 */
final class RequireOwnPassword
{
    public const string ROUTE = 'lynx.admin.account.new-password';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->user()?->getAuthIdentifier();
        $isTemporary = $id !== null && TemporaryPassword::query()->where('user_id', Value::string($id))->exists();

        if ($isTemporary && ! $request->routeIs(self::ROUTE, self::ROUTE.'.save')) {
            return redirect()->route(self::ROUTE);
        }

        return $next($request);
    }
}
