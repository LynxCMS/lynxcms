<?php

declare(strict_types=1);

namespace LynxCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LynxCms\Core\Http\PageAttributes;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal Marks the Lynx catch-all route so DetectLocale lets the URL decide the language.
 */
final class MarkPageRoute
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(PageAttributes::PAGE_ROUTE, true);

        return $next($request);
    }
}
