<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers for admin and sign-in screens: other sites may not frame them (clickjacking), and links
 * leaving the admin send no address. Pages of the site itself may still frame admin screens. A
 * response that sets its own policy, such as an uploaded SVG logo's, keeps it.
 *
 * @internal
 */
final class SendAdminHeaders
{
    private const array HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self'",
        'Referrer-Policy' => 'same-origin',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            return $response;
        }

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
