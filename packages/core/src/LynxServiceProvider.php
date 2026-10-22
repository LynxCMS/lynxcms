<?php

declare(strict_types=1);

namespace LynxCms\Core;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Actions\Runner\HookRegistry;
use LynxCms\Core\Activity\ActivityRecorder;
use LynxCms\Core\Agents\ReadToolRegistry;
use LynxCms\Core\Components\ComponentRegistry;
use LynxCms\Core\Console\BackupCommand;
use LynxCms\Core\Console\ScheduleRunCommand;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Fields\FormatterChain;
use LynxCms\Core\Fields\FormatterRegistry;
use LynxCms\Core\Http\Controllers\PageController;
use LynxCms\Core\Http\Middleware\DetectLocale;
use LynxCms\Core\Http\Middleware\MarkPageRoute;
use LynxCms\Core\Http\Middleware\RedirectDefaultLocale;
use LynxCms\Core\Http\Middleware\RememberLocale;
use LynxCms\Core\Indexes\IndexesServiceProvider;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Languages\TranslationLocales;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageAncestry;
use LynxCms\Core\Pages\PageGates;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\PathResolver;
use LynxCms\Core\Pages\SettingsPages;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Pages\UnknownFields;
use LynxCms\Core\Pages\UnpublishedBranches;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\Core\Search\SearchServiceProvider;
use LynxCms\Core\Strings\StringCatalog;
use LynxCms\Core\Strings\StringDefinition;
use LynxCms\Core\Strings\StringStore;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

final class LynxServiceProvider extends ServiceProvider
{
    private function registerRouting(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('lynx.locale', DetectLocale::class);
        $router->aliasMiddleware('lynx.locale-session', RememberLocale::class);
        $router->aliasMiddleware('lynx.locale-redirect', RedirectDefaultLocale::class);

        if (! config('lynx.routes.enabled', true)) {
            return;
        }

        $middleware = [...Value::strings(config('lynx.routes.middleware', ['web'])), MarkPageRoute::class];

        if (config('lynx.localization.redirect_default_prefix', true)) {
            $middleware[] = RedirectDefaultLocale::class;
        }

        $middleware[] = DetectLocale::class;

        if (config('lynx.localization.remember', true)) {
            $middleware[] = RememberLocale::class;
        }

        $this->app->booted(function () use ($router, $middleware): void {
            $router->fallback(PageController::class)->middleware($middleware)->name('lynx.page');
        });
    }

    private function registerBladeDirectives(): void
    {
        Blade::directive('hreflang', fn (string $page): string => "<?php echo app(\\LynxCms\\Core\\View\\HreflangBuilder::class)->html({$page}); ?>");
        Blade::directive('field', fn (string $value): string => "<?php echo app(\\LynxCms\\Core\\View\\FieldRenderer::class)->render({$value}); ?>");
        Blade::directive('matrix', fn (string $items): string => "<?php echo app(\\LynxCms\\Core\\View\\MatrixRenderer::class)->render({$items}, \$page ?? null); ?>");
        // The view's folder decides the scope of calls that name none (ADR-0051), known while compiling.
        Blade::directive('string', fn (string $args): string => "<?php echo app(\\LynxCms\\Core\\Strings\\StringRenderer::class)->renderIn('"
            .StringDefinition::defaultScopeFor((string) Blade::getPath())."', \$page ?? null, {$args}); ?>");
        Blade::directive('lynxComponent', fn (string $args): string => "<?php echo app(\\LynxCms\\Core\\View\\ComponentRenderer::class)->render({$args}); ?>");
    }

    /** Request-level caches flushed after every action. */
    private const array FLUSHABLE = [
        LanguageRegistry::class,
        FieldRegistry::class,
        TemplateRegistry::class,
        PageLoader::class,
        PathResolver::class,
        SettingsPages::class,
        UnpublishedBranches::class,
        FormatterChain::class,
        PageAncestry::class,
        Authorizer::class,
        Trash::class,
    ];

    public function register(): void
    {
        $this->app->scoped(UnknownFields::class);
        // Page gates and visibility rules (ADR-0062): registered by modules at boot.
        $this->app->singleton(PageGates::class);
        $this->app->singleton(PageVisibility::class);
        $this->app->register(SearchServiceProvider::class);
        $this->app->register(IndexesServiceProvider::class);
        $this->mergeConfigFrom(__DIR__.'/../config/lynx.php', 'lynx');

        // The locale of the "default" language when it has none: the app's configured locale,
        // captured before LanguageContext starts changing app.locale per request.
        $this->app->booting(function (): void {
            config(['lynx.base_locale' => config('lynx.base_locale') ?? config('app.locale', 'en')]);
        });

        $this->app->singleton(StringStore::class);

        $this->app->singleton(StringCatalog::class);

        $this->app->singleton(Lynx::class);
        $this->app->singleton(ActionRunner::class);
        $this->app->singleton(HookRegistry::class);
        $this->app->singleton(ActionRegistry::class);
        $this->app->singleton(ReadToolRegistry::class);
        $this->app->singleton(FieldTypeRegistry::class);
        $this->app->singleton(FormatterRegistry::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(ComponentRegistry::class);

        foreach (self::FLUSHABLE as $service) {
            $this->app->scoped($service);
        }

        $this->app->tag(self::FLUSHABLE, 'lynx.flushable');
        $this->app->scoped(LanguageContext::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'lynx');

        $this->publishes([__DIR__.'/../config/lynx.php' => config_path('lynx.php')], 'lynx-config');

        $this->app->make(FieldTypeRegistry::class)->register(...CoreRegistrations::FIELD_TYPES);

        $this->app->make(FormatterRegistry::class)->register(...CoreRegistrations::FORMATTERS);

        $this->app->make(ActionRegistry::class)->register(...CoreRegistrations::ACTIONS);

        $this->app->make(HookRegistry::class)->after('*', $this->app->make(ActivityRecorder::class)(...));

        $this->registerRouting();
        $this->registerBladeDirectives();
        $this->registerGate();

        if ($this->app->runningInConsole()) {
            $this->commands(CoreRegistrations::COMMANDS);
        }

        // Scheduled publishing (ADR-0036): due entries are applied within a minute.
        // Regional locales (pt-PT) also read the bare language's translations (lang/pt).
        $this->callAfterResolving('translator', function (Translator $translator): void {
            $translator->determineLocalesUsing(TranslationLocales::expand(...));
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(ScheduleRunCommand::class)->everyMinute()->withoutOverlapping();

            // Content backups (ADR-0040), when lynx.backups.schedule asks for them.
            match (config('lynx.backups.schedule')) {
                'hourly' => $schedule->command(BackupCommand::class)->hourly()->withoutOverlapping(),
                'daily' => $schedule->command(BackupCommand::class)->daily()->withoutOverlapping(),
                default => null,
            };
        });
    }

    /**
     * Lynx permissions as Laravel abilities named "lynx.<permission>" (ADR-0033):
     * Gate::allows('lynx.pages.edit', $page), @can('lynx.pages.edit', $page). The optional
     * argument is the page acted on, or a template.
     */
    private function registerGate(): void
    {
        Gate::before(function (Authenticatable $user, string $ability, array $arguments): ?bool {
            $permission = str_starts_with($ability, 'lynx.') ? substr($ability, 5) : '';

            if (! $this->app->make(PermissionRegistry::class)->has($permission)) {
                return null;
            }

            $id = $user->getAuthIdentifier();
            $subject = $arguments[0] ?? null;

            return $this->app->make(Authorizer::class)->allows(
                Actor::user(is_int($id) || is_string($id) ? $id : ''),
                $permission,
                $subject instanceof Page ? $subject : null,
                $subject instanceof Template ? $subject : null,
            );
        });
    }
}
