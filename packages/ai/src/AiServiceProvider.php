<?php

declare(strict_types=1);

namespace LynxCms\Ai;

use Illuminate\Support\ServiceProvider;
use LynxCms\Ai\Console\AskCommand;
use LynxCms\Ai\Insights\AgentInsights;
use LynxCms\Ai\Translation\LaravelAiTranslationProvider;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Lynx;
use LynxCms\Core\Translation\TranslationProviders;
use LynxCms\Insights\InsightSourceRegistry;

/** @internal */
final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app->bound(TranslationProvider::class)) {
            $this->app->bind(TranslationProvider::class, LaravelAiTranslationProvider::class);
        }
    }

    public function boot(): void
    {
        Lynx::macro('ai', fn (): LynxAi => app(LynxAi::class));
        $this->app->make(TranslationProviders::class)->register('laravel-ai', fn (): TranslationProvider => $this->app->make(LaravelAiTranslationProvider::class));
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'lynx-ai');
        // The Insights Agent, only when lynxcms/insights is installed: without it the class is never loaded.
        $this->callAfterResolving(InsightSourceRegistry::class, fn (InsightSourceRegistry $sources) => $sources->add(AgentInsights::class));

        if ($this->app->runningInConsole()) {
            $this->commands([AskCommand::class]);
        }
    }
}
