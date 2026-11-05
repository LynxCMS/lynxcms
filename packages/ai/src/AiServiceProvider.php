<?php

declare(strict_types=1);

namespace LynxCms\Ai;

use Illuminate\Support\ServiceProvider;
use LynxCms\Ai\Console\AskCommand;
use LynxCms\Ai\Translation\LaravelAiTranslationProvider;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Lynx;
use LynxCms\Core\Translation\TranslationProviders;

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

        if ($this->app->runningInConsole()) {
            $this->commands([AskCommand::class]);
        }
    }
}
