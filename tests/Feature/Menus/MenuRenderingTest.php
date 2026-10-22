<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->menus()->create('top', 'Top');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true]);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => '<b>Docs</b>', 'url' => 'https://example.com', 'target' => '_blank']);
});

it('renders a plain nested list by default', function (): void {
    $html = Blade::render('<x-lynx-menu name="top" class="main" />');

    expect($html)->toContain('<nav class="lynx-menu lynx-menu-list main" aria-label="Top">')
        ->toContain('<a href="/courses"')
        ->toContain('<a href="/courses/alpha"')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener"')
        ->toContain('&lt;b&gt;Docs&lt;/b&gt;')
        ->not->toContain('<b>Docs</b>');
});

it('renders the dropdown and accordion presets', function (string $view, string $marker): void {
    expect(Blade::render("<x-lynx-menu name=\"top\" view=\"lynx-menus::menus.{$view}\" />"))->toContain($marker);
})->with([
    ['dropdown', 'lynx-menu-dropdown'],
    ['accordion', '<details'],
]);

it('renders nothing for a missing menu', function (): void {
    expect(trim(Blade::render('<x-lynx-menu name="nope" />')))->toBe('');
});

it('passes the menu to a custom view', function (): void {
    view()->addNamespace('custom', __DIR__.'/views');

    expect(Blade::render('<x-lynx-menu name="top" view="custom::menu" />'))->toContain('custom: Top (2 items)');
});

it('names a missing view in its error', function (): void {
    expect(fn () => Blade::render('<x-lynx-menu name="top" view="nope::menu" />'))->toThrow(Exception::class, 'Menu view [nope::menu] does not exist.');
});

it('renders icons as classes by default', function (): void {
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'Iconic', 'icon_before' => 'fa fa-star', 'icon_after' => 'caret']);

    expect(Blade::render('<x-lynx-menu name="top" />'))
        ->toContain('<span class="lynx-menu-icon lynx-menu-icon-before fa fa-star" aria-hidden="true"></span>Iconic<span class="lynx-menu-icon lynx-menu-icon-after caret" aria-hidden="true"></span>');
});

it('renders icons with the app\'s own renderer', function (): void {
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'Iconic', 'icon_before' => 'heroicon-o-star']);
    lynx()->menus()->renderIconsUsing(fn (string $icon, string $position): string => "<svg data-icon=\"{$icon}\" data-position=\"{$position}\"></svg>");

    expect(Blade::render('<x-lynx-menu name="top" />'))->toContain('<svg data-icon="heroicon-o-star" data-position="before"></svg>Iconic');
});

it('gives the dropdown a menu button that opens the list as a popover on small screens', function (): void {
    $html = Blade::render('<x-lynx-menu name="top" view="lynx-menus::menus.dropdown" />');

    preg_match('/popovertarget="([^"]+)"/', $html, $button);
    preg_match('/<div id="([^"]+)" popover class="lynx-menu-panel"/', $html, $panel);

    expect($button[1] ?? null)->not->toBeNull()
        ->and($panel[1] ?? null)->toBe($button[1])
        ->and($html)->toContain('class="lynx-menu-toggle"')
        ->toContain('aria-label="Top"');
});

it('gives every rendered dropdown its own popover id', function (): void {
    $html = Blade::render('<x-lynx-menu name="top" view="lynx-menus::menus.dropdown" /><x-lynx-menu name="top" view="lynx-menus::menus.dropdown" />');

    preg_match_all('/popover class="lynx-menu-panel"/', $html, $panels);
    preg_match_all('/<div id="([^"]+)" popover/', $html, $ids);

    expect($panels[0])->toHaveCount(2)
        ->and(array_unique($ids[1]))->toHaveCount(2);
});

it('labels the menu button through Laravel translations', function (): void {
    app('translator')->addJsonPath(__DIR__.'/lang');
    app()->setLocale('pt_PT');

    expect(Blade::render('<x-lynx-menu name="top" view="lynx-menus::menus.dropdown" />'))->toContain('>Menu principal<');
});
