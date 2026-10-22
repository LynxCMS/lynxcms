<?php

declare(strict_types=1);

namespace LynxCms\PagePassword;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Events\PageDeleted;
use LynxCms\Core\Events\PageMoved;
use LynxCms\Core\Events\PageRestored;
use LynxCms\Core\Events\PageSaved;
use LynxCms\Core\Events\PageTemplateSwitched;
use LynxCms\Core\Events\TemplateUpdated;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGates;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\PagePassword\Access\PasswordGate;
use LynxCms\PagePassword\Access\PasswordVisibility;
use LynxCms\PagePassword\Access\ProtectedRoots;
use LynxCms\PagePassword\Access\Unlocks;
use LynxCms\PagePassword\Actions\DisablePagePassword;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\Actions\InstallPagePassword;
use LynxCms\PagePassword\Admin\AdminIntegration;
use LynxCms\PagePassword\Fields\PasswordType;
use LynxCms\PagePassword\Http\UnlockController;
use LynxCms\Sitemap\SitemapFilters;
use Symfony\Component\HttpFoundation\Response;

/**
 * The pagePassword module (documentation/plans/page-passwords.md): the password field type, the
 * actions that add it to templates, and the visibility rule that hides locked pages.
 */
final class PagePasswordServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    /** Changes after which the protected pages are read again. */
    private const array CHANGES = [PageSaved::class, PageDeleted::class, PageMoved::class, PageRestored::class, PageTemplateSwitched::class, TemplateUpdated::class];

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT.'/config/lynx-page-password.php', 'lynx-page-password');
        $this->app->singleton(ProtectedRoots::class);
        $this->app->singleton(Unlocks::class);
        $this->app->singleton(PasswordVisibility::class);
    }

    public function boot(): void
    {
        $this->app->make(FieldTypeRegistry::class)->register(PasswordType::class);
        $this->app->make(ActionRegistry::class)->register(InstallPagePassword::class, EnablePagePassword::class, DisablePagePassword::class);
        $this->app->make(PageVisibility::class)->add($this->app->make(PasswordVisibility::class));

        Event::listen(self::CHANGES, function (): void {
            $this->app->make(ProtectedRoots::class)->flush();
            $this->app->make(PasswordVisibility::class)->flush();
        });

        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-page-password');
        $this->loadTranslationsFrom(self::ROOT.'/lang', 'lynx-page-password');
        $this->app->make(PageGates::class)->add(fn (Page $page, Request $request): ?Response => $this->app->make(PasswordGate::class)($page, $request));
        Route::post('lynx-page-password/unlock', UnlockController::class)->middleware('web')->name('lynx.page-password.unlock');

        // Unlocked protected pages are never cached or indexed either.
        Event::listen(RequestHandled::class, function (RequestHandled $event): void {
            if ($event->request->attributes->get(PasswordGate::PROTECTED) === true) {
                PasswordGate::private($event->response);
            }
        });

        if (class_exists(FieldInputs::class) && $this->app->bound(FieldInputs::class)) {
            AdminIntegration::register();
        }

        // Protected pages never go in the sitemap, whoever builds it (it is cached for everyone).
        if (class_exists(SitemapFilters::class) && $this->app->bound(SitemapFilters::class)) {
            $this->app->make(SitemapFilters::class)->exclude(fn (Page $page): bool => $this->app->make(PasswordVisibility::class)->nearestRoot((int) $page->id) !== null);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([self::ROOT.'/config/lynx-page-password.php' => config_path('lynx-page-password.php')], 'lynx-page-password-config');
            $this->publishes([self::ROOT.'/resources/views/form.blade.php' => resource_path('views/vendor/lynx-page-password/form.blade.php')], 'lynx-page-password-views');
        }
    }
}
