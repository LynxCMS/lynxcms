<?php

declare(strict_types=1);

namespace LynxCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LynxCms\Core\Actions\Runner\ActionRunner;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware: actions run in a web request with nobody signed in act as a visitor, who
 * holds only what is open to everyone, never as the console (ADR-0078).
 *
 * @internal
 */
final readonly class ServeAsVisitor
{
    public function __construct(private ActionRunner $runner) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $this->runner->servingRequest(fn (): Response => $next($request));
    }
}
