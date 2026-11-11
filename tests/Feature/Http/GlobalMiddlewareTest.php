<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Artisan;
use LynxCms\Cache\Http\PageCacheMiddleware;
use LynxCms\Core\Http\GlobalMiddleware;
use LynxCms\Core\Http\Middleware\ServeAsVisitor;

/*
 * Lynx adds middleware that runs on every request (ServeAsVisitor in core, the page cache in
 * lynxcms/cache) through Laravel's own HTTP kernel, the only way a package can. An app whose
 * kernel is not Laravel's would lose them without a word, so GlobalMiddleware remembers what it
 * could not add and lynx:doctor names it, with the line that adds it in bootstrap/app.php.
 */

/** An HTTP kernel that is not Laravel's Foundation kernel, as a heavily customized app might have. */
function foreignKernel(): Kernel
{
    return new class implements Kernel
    {
        public function bootstrap(): void {}

        public function handle($request): never
        {
            throw new LogicException('Not used.');
        }

        public function terminate($request, $response): void {}

        public function getApplication(): Application
        {
            return app();
        }
    };
}

/** @return list<array{severity: string, message: string, fix: string|null}> */
function globalMiddlewareFindings(int $exit): array
{
    expect(Artisan::call('lynx:doctor', ['--json' => true]))->toBe($exit);

    return collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->firstWhere('key', 'global-middleware')['findings'];
}

beforeEach(function (): void {
    courseSite();
});

it('runs the visitor marker on every request, and the page cache before anything else', function (): void {
    $kernel = app(Kernel::class);

    expect($kernel->hasMiddleware(ServeAsVisitor::class))->toBeTrue()
        ->and($kernel->getGlobalMiddleware()[0])->toBe(PageCacheMiddleware::class);
});

it('reports nothing when Laravel\'s kernel took every middleware', function (): void {
    expect(globalMiddlewareFindings(exit: 0))->toBe([]);
});

it('names the middleware a kernel of another kind could not take, and how to add it', function (): void {
    app()->instance(Kernel::class, foreignKernel());
    $middleware = app(GlobalMiddleware::class);
    $middleware->append(ServeAsVisitor::class);
    $middleware->prepend(PageCacheMiddleware::class);

    $findings = globalMiddlewareFindings(exit: 1);

    expect(array_column($findings, 'severity'))->toBe(['error', 'error'])
        ->and($findings[0]['fix'])->toContain('bootstrap/app.php')->toContain('->append(\\'.ServeAsVisitor::class.'::class)')
        ->and($findings[1]['fix'])->toContain('->prepend(\\'.PageCacheMiddleware::class.'::class)');
});
