<?php

declare(strict_types=1);

namespace LynxCms\Insights;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\Actions\ApplyInsightStep;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\ArchiveInsight;
use LynxCms\Insights\Actions\DismissInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Actions\RunInsightStep;
use LynxCms\Insights\Actions\StopInsight;
use LynxCms\Insights\Admin\AdminIntegration;
use LynxCms\Insights\Console\InsightsCommand;
use LynxCms\Insights\Sources\MissingTranslations;
use Override;

/**
 * The insights module (ADR-0155): reports of suggested changes from the registered sources, and
 * the actions that approve, dismiss or archive them. With the admin, the Insights screen.
 *
 * @internal
 */
final class InsightsServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT.'/config/lynx-insights.php', 'lynx-insights');
        $this->app->singleton(InsightSourceRegistry::class);
    }

    public function boot(): void
    {
        $this->app->make(ActionRegistry::class)->register(GenerateInsights::class, ApproveInsight::class, DismissInsight::class, ArchiveInsight::class, ApplyInsightStep::class, RunInsightStep::class, StopInsight::class);
        $permissions = $this->app->make(PermissionRegistry::class);
        $permissions->register('insights.view', 'View insight reports', requires: ['pages.view']);
        $permissions->register('insights.review', 'Approve, dismiss and archive insights, and write reports', requires: ['insights.view']);
        $this->app->make(InsightSourceRegistry::class)->add(MissingTranslations::class);
        $this->loadMigrationsFrom(self::ROOT.'/database/migrations');
        $this->loadTranslationsFrom(self::ROOT.'/lang', 'lynx-insights');
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-insights');
        $this->scheduleReports();

        if ($this->app->bound(Navigation::class)) {
            AdminIntegration::register();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([self::ROOT.'/config/lynx-insights.php' => config_path('lynx-insights.php')], 'lynx-insights-config');
            $this->commands([InsightsCommand::class]);
        }
    }

    private function scheduleReports(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $when = Value::string(config('lynx-insights.schedule'));

            if ($when === '') {
                return;
            }

            $event = $schedule->command('lynx:insights')->withoutOverlapping();
            $when === 'monthly' ? $event->monthly() : $event->cron($when);
        });
    }
}
