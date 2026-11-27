<?php

declare(strict_types=1);

namespace LynxCms\Insights\Admin;

use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Home\HomeScreen;
use LynxCms\Admin\Navigation;

/**
 * Adds the Insights screen to the admin, with the number of open insights in the sidebar and on
 * the home screen. Called only when lynxcms/admin is installed.
 *
 * @internal
 */
final class AdminIntegration
{
    private const int NAVIGATION_ORDER = 35;

    private const int HOME_ORDER = 40;

    public static function register(): void
    {
        Livewire::component('lynx-insights.screen', InsightsScreen::class);
        $canView = fn (): bool => app(AdminAccess::class)->allows('insights.view');
        $open = fn (): ?int => app(ReportView::class)->openCount() ?: null;
        app(Navigation::class)->add('lynx-insights::admin.title', 'lynx.admin.insights', order: self::NAVIGATION_ORDER, visible: $canView, icon: 'lightbulb', badge: $open);
        app(HomeScreen::class)->stat('insights', 'lynx-insights::admin.home_stat', fn (): int => app(ReportView::class)->openCount(), url: fn (): string => route('lynx.admin.insights'), icon: 'lightbulb', visible: $canView, order: self::HOME_ORDER);
        Admin::routes(fn () => Admin::screen('insights', 'lynx-insights::admin.index', 'insights.view')->name('insights'));
    }
}
