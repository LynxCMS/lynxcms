<?php

declare(strict_types=1);

namespace LynxCms\Cache\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LynxCms\Cache\Islands\Island;
use LynxCms\Cache\Islands\IslandRenderer;
use LynxCms\Cache\Pages\TokenRefresh;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a stored page asks for after loading (never cached), and the script that asks.
 *
 * @internal
 */
final readonly class TokenRefreshController
{
    /** A day: the script changes only with the package. */
    private const int SCRIPT_MAX_AGE = 86400;

    /** Islands rendered per request, so one request cannot ask for unlimited work. */
    private const int MAX_ISLANDS = 20;

    /**
     * What a stored page fills in after loading: the visitor's token now, island HTML later. A
     * GET, so asking needs no token; the web middleware also sets the session and XSRF cookies.
     */
    public function dynamic(Request $request, IslandRenderer $renderer): JsonResponse
    {
        $islands = array_map(
            fn (string $payload): ?string => ($island = Island::decrypt($payload)) === null ? null : $renderer->render($island),
            array_slice(Value::strings($request->query('islands')), 0, self::MAX_ISLANDS),
        );

        return response()->json(['token' => $request->session()->token(), 'islands' => $islands, 'assets' => $renderer->assets()])
            ->header('Cache-Control', 'no-store, private');
    }

    public function script(TokenRefresh $refresh): Response
    {
        return response($refresh->script())
            ->header('Content-Type', 'text/javascript; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age='.self::SCRIPT_MAX_AGE);
    }
}
