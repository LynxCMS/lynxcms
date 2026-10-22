<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Actions\Roles\AssignRoles;

/*
 * The sidebar groups setup screens under Settings and Access, both closed unless the current
 * screen is inside them.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('lists the screens in their sections', function (): void {
    $this->get('/lynx/pages')->assertOk()->assertSeeInOrder([
        'Pages', 'Forms', 'Menus', 'Site texts',
        'Settings', 'Fields', 'Templates', 'SEO', 'Sitemap', 'Languages',
        'Access', 'Roles', 'Users',
    ]);
});

it('keeps groups closed unless the current screen is in them', function (): void {
    $this->get('/lynx/pages')->assertSee('data-nav-group="settings" data-open="false"', false)
        ->assertSee('data-nav-group="access" data-open="false"', false);

    $this->get('/lynx/languages')->assertSee('data-nav-group="settings" data-open="true"', false)
        ->assertSee('data-nav-group="access" data-open="false"', false);
});

it('groups items and leaves out groups with nothing visible', function (): void {
    $navigation = (new Navigation)
        ->group('tools', 'Tools', order: 50, icon: 'wrench')
        ->add('Pages', 'pages', order: 10)
        ->add('Export', 'export', order: 20, group: 'tools')
        ->add('Import', 'import', order: 10, group: 'tools')
        ->group('hidden', 'Hidden', order: 60)
        ->add('Secret', 'secret', visible: fn (): bool => false, group: 'hidden');

    $menu = $navigation->menu();

    expect(array_column($menu, 'label'))->toBe(['Pages', 'Tools'])
        ->and(array_column($menu[1]['items'] ?? [], 'label'))->toBe(['Import', 'Export'])
        ->and(array_column($navigation->items(), 'label'))->toBe(['Pages', 'Import', 'Export']);
});
