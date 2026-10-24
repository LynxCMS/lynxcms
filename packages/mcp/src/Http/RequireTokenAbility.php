<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Http;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Response;

/**
 * The MCP web route takes only API tokens made for it: a Sanctum token must carry the ability in
 * lynx.mcp.web.ability ("lynx:mcp"), so a token the app issued for something else (a mobile app,
 * an integration) is not also a key to the site's content. Requests signed in another way, such
 * as a session, pass.
 *
 * @internal
 */
final class RequireTokenAbility
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ability = Value::string(config('lynx.mcp.web.ability', 'lynx:mcp'));
        $user = $request->user();
        $token = is_object($user) && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($ability !== '' && $token instanceof Model && ! in_array($ability, Value::strings($token->getAttribute('abilities')), true)) {
            abort(403, "This token cannot use the MCP server: create one with the [{$ability}] ability.");
        }

        return $next($request);
    }
}
