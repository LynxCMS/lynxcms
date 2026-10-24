<?php

declare(strict_types=1);

namespace LynxCms\Tests;

use BladeUI\Icons\BladeIconsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passkeys\PasskeysServiceProvider;
use Livewire\LivewireManager;
use Livewire\LivewireServiceProvider;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Ai\AiServiceProvider;
use LynxCms\Cache\CacheServiceProvider;
use LynxCms\Core\LynxServiceProvider;
use LynxCms\Forms\FormsServiceProvider;
use LynxCms\Mcp\McpServiceProvider;
use LynxCms\Media\MediaServiceProvider;
use LynxCms\Menus\MenusServiceProvider;
use LynxCms\PagePassword\PagePasswordServiceProvider;
use LynxCms\Seo\SeoServiceProvider;
use LynxCms\Sitemap\SitemapServiceProvider;
use LynxCms\StaticSite\StaticSiteServiceProvider;
use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Spatie\Honeypot\HoneypotServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;
    use WithWorkbench;

    /**
     * Livewire keeps some per request state in static properties, which outlive the application
     * between tests in one process; whichever test ran before could leak into this one.
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->app?->make(LivewireManager::class)->flushState();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Laravel\Mcp\Server\McpServiceProvider::class,
            \Laravel\Ai\AiServiceProvider::class,
            LynxServiceProvider::class,
            McpServiceProvider::class,
            AiServiceProvider::class,
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeLucideIconsServiceProvider::class,
            AdminServiceProvider::class,
            FortifyServiceProvider::class,
            PasskeysServiceProvider::class,
            MenusServiceProvider::class,
            MediaServiceProvider::class,
            HoneypotServiceProvider::class,
            FormsServiceProvider::class,
            SeoServiceProvider::class,
            SitemapServiceProvider::class,
            PagePasswordServiceProvider::class,
            CacheServiceProvider::class,
            StaticSiteServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('auth.providers.users.model', User::class);
        $connection = env('DB_CONNECTION', 'sqlite');
        $app['config']->set('database.default', $connection);

        if ($connection === 'pgsql') {
            $app['config']->set('database.connections.pgsql', array_merge($app['config']->get('database.connections.pgsql'), [
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                // phpunit.xml.dist sets DB_DATABASE to :memory: for SQLite; PostgreSQL then uses lynx_test.
                'database' => in_array(env('DB_DATABASE'), [null, ':memory:'], true) ? 'lynx_test' : env('DB_DATABASE'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'postgres'),
            ]));
        }

        // Most tests act as the site's owner without giving them a role, so the suite keeps the
        // old open mode; tests of the closed default turn it off (ADR-0078).
        $app['config']->set('lynx.permissions.open_until_roles', true);

        // DB_PREFIX simulates a host app that uses Laravel's table prefix setting.
        $app['config']->set("database.connections.{$connection}.prefix", env('DB_PREFIX', ''));
    }
}
