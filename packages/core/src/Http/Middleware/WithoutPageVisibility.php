<?php

declare(strict_types=1);

namespace LynxCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use Symfony\Component\HttpFoundation\Response;

/**
 * Switches page visibility rules off for the request (ADR-0062): the admin's routes and the MCP
 * web route carry it, so editors and agents see every page.
 *
 * @api
 */
final readonly class WithoutPageVisibility
{
    public function __construct(private PageVisibility $visibility) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $this->visibility->withoutRules(fn (): Response => $next($request));
    }
}
