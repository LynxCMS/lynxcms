<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor\Checks;

use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Http\GlobalMiddleware;

/**
 * Middleware Lynx adds to every request, which an app whose HTTP kernel is not Laravel's
 * Foundation kernel does not get (GlobalMiddleware). Each one names the line that adds it.
 *
 * @internal
 */
final readonly class GlobalMiddlewareRegistered implements Check
{
    public function __construct(private GlobalMiddleware $middleware) {}

    public function key(): string
    {
        return 'global-middleware';
    }

    public function label(): string
    {
        return 'Lynx\'s middleware runs on every request';
    }

    public function run(): array
    {
        return array_map(fn (array $missing): Finding => new Finding(
            Severity::Error,
            "[{$missing['middleware']}] is not in the app's global middleware: its HTTP kernel is not Laravel's.",
            "In bootstrap/app.php: ->withMiddleware(fn (Middleware \$middleware) => \$middleware->{$missing['position']}(\\{$missing['middleware']}::class))",
        ), $this->middleware->missing());
    }
}
