<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use LynxCms\Core\Lynx;
use Symfony\Component\Finder\Finder;

beforeEach(fn (): Lynx => courseSite());

it('keeps guests out of the admin', function (): void {
    $this->getJson('/lynx/pages')->assertUnauthorized();
});

it('sends the admin\'s own address to the page tree', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx')->assertRedirect(route('lynx.admin.pages'));
});

it('sends guests asking for the admin to sign in first', function (): void {
    $this->get('/lynx')->assertRedirect(route('login'));
});

it('keeps users the admin gate denies out of the admin', function (): void {
    Gate::define('accessLynxAdmin', fn (): bool => false);

    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/pages')->assertForbidden();
});

it('renders the page tree screen', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]))
        ->get('/lynx/pages')
        ->assertOk()
        ->assertSee('Courses')
        ->assertSee('data-lynx-theme="system"', false)
        ->assertSee('/lynx/assets/admin.css?v=', false);
});

it('applies the configured theme and extra stylesheets', function (): void {
    config(['lynx-admin.theme' => 'dark', 'lynx-admin.stylesheets' => ['/css/brand.css']]);

    $this->actingAs(new GenericUser(['id' => 1]))
        ->get('/lynx/pages')
        ->assertSee('data-lynx-theme="dark"', false)
        ->assertSee('class="dark"', false)
        ->assertSee('<link rel="stylesheet" href="/css/brand.css">', false);
});

it('offers light, dark and system appearance and account security in the user menu', function (): void {
    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ana']))
        ->get('/lynx/pages')
        ->assertSeeInOrder(['Ana', 'Appearance', 'Light', 'Dark', 'System', 'Security'])
        ->assertSee('href="'.route('lynx.admin.account').'"', false)
        ->assertSee('lynxColorScheme.set(radioValue)', false);
});

it('links to the site from the top bar', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]))
        ->get('/lynx/pages')
        ->assertSeeInOrder(['href="'.url('/').'"', 'target="_blank"', 'aria-label="View site"'], false);
});

it('applies the appearance a user picked before the stylesheet loads', function (): void {
    config(['lynx-admin.theme' => 'light']);

    $html = $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/pages')->getContent();

    expect(strpos((string) $html, "localStorage.getItem('lynx:color-scheme')"))->toBeInt()->toBeLessThan(strpos((string) $html, 'admin.css'));
});

it('serves the prebuilt stylesheet', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]))
        ->get('/lynx/assets/admin.css')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=utf-8');
});

it('uses only themeable colour utilities in admin views', function (): void {
    // Raw palette classes would bypass BlatUI's tokens and break theming. Black and white are
    // kept for overlays and text on solid fills (admin.css).
    $palette = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
    $pattern = '/\b(?:bg|text|border|ring|outline|fill|stroke|from|to|via|divide|decoration|accent|caret|shadow)-(?:'.$palette.')(?:-\d{2,3})?\b/';
    $offenders = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 3).'/packages/admin/resources/views') as $file) {
        if (preg_match($pattern, $file->getContents(), $match) === 1) {
            $offenders[] = $file->getRelativePathname().': '.$match[0];
        }
    }

    expect($offenders)->toBe([]);
});
