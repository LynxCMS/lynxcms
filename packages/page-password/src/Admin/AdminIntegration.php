<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Admin;

use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Admin\EditorLinks;
use LynxCms\Admin\Navigation;
use LynxCms\Admin\Tree\TreeMarkers;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\PagePassword\Access\PasswordVisibility;
use LynxCms\PagePassword\PagePasswordSchema;

/**
 * The admin side, when lynxcms/admin is installed: the password input, Settings > Page passwords,
 * a key beside protected pages in the tree, and a link to the page that protects one from above.
 *
 * @internal
 */
final class AdminIntegration
{
    /** Between Sitemap and Languages in the Settings section. */
    private const int NAVIGATION_ORDER = 45;

    public static function register(): void
    {
        app(FieldInputs::class)->register(PagePasswordSchema::TYPE, 'lynx-page-password::inputs.password', PasswordConverter::class, 'lynx-page-password::values.password');

        Livewire::component('lynx-page-password.settings', PagePasswordSettings::class);
        $canManage = fn (): bool => app(AdminAccess::class)->allows('schema.manage');
        app(Navigation::class)->add('Page passwords', 'lynx.admin.page-passwords', order: self::NAVIGATION_ORDER, visible: $canManage, icon: 'key-round', group: Navigation::SETTINGS);
        Admin::routes(fn () => Admin::screen('page-passwords', 'lynx-page-password::admin.index', 'schema.manage')->name('page-passwords'));

        $visibility = app(PasswordVisibility::class);
        app(TreeMarkers::class)->add('page-password', fn (Page $page): ?array => $visibility->nearestRoot((int) $page->id) === (int) $page->id
            ? ['icon' => 'key-round', 'label' => 'Password protected']
            : null);
        app(EditorLinks::class)->add(function (Page $page) use ($visibility): ?array {
            $root = $visibility->nearestRoot((int) $page->id);
            $protector = $root !== null && $root !== (int) $page->id ? lynx()->pages->get($root) : null;

            return $protector instanceof Page ? ['label' => 'Protected by '.Value::string($protector->getUnformatted('title')), 'url' => route('lynx.admin.pages.edit', $root)] : null;
        });
    }
}
