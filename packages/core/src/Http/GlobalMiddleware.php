<?php

declare(strict_types=1);

namespace LynxCms\Core\Http;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as FoundationKernel;

/**
 * Middleware Lynx runs on every request, added from service providers. An app lists its own in
 * bootstrap/app.php, which a package cannot edit, so this goes through Laravel's HTTP kernel:
 * pushMiddleware() and prependMiddleware() belong to its Foundation kernel, not to the Kernel
 * contract. An app with another kernel gets nothing added; what was left out is kept for
 * lynx:doctor, which says how to add it by hand.
 *
 * @internal
 */
final class GlobalMiddleware
{
    public const string APPEND = 'append';

    public const string PREPEND = 'prepend';

    /** @var list<array{middleware: class-string, position: self::APPEND|self::PREPEND}> */
    private array $missing = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string  $middleware  runs after the app's global middleware */
    public function append(string $middleware): void
    {
        $kernel = $this->kernel();

        if ($kernel instanceof FoundationKernel) {
            $kernel->pushMiddleware($middleware);

            return;
        }

        $this->missing[] = ['middleware' => $middleware, 'position' => self::APPEND];
    }

    /** @param  class-string  $middleware  runs before any other middleware */
    public function prepend(string $middleware): void
    {
        $kernel = $this->kernel();

        if ($kernel instanceof FoundationKernel) {
            $kernel->prependMiddleware($middleware);

            return;
        }

        $this->missing[] = ['middleware' => $middleware, 'position' => self::PREPEND];
    }

    /**
     * The middleware the app's kernel could not take, in the order they were added.
     *
     * @return list<array{middleware: class-string, position: self::APPEND|self::PREPEND}>
     */
    public function missing(): array
    {
        return $this->missing;
    }

    private function kernel(): Kernel
    {
        return $this->container->make(Kernel::class);
    }
}
