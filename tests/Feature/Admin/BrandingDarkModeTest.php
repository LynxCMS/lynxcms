<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Admin\Branding\AccentContrast;
use LynxCms\Admin\Branding\Branding;
use LynxCms\Admin\Branding\SaveBranding;
use LynxCms\Admin\Livewire\BrandingForm;

/*
 * The branding in dark mode (ADR-0084): its own logo, sign-in background and accent. Each falls
 * back to the light one, so a site that sets none looks as before. Text on a colour stays readable
 * by itself: white or near-black, whichever contrasts more.
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
});

/** A one-pixel PNG as a data URL; $variant changes its bytes, so it stores as another file. */
function darkModePng(string $variant = ''): string
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    return 'data:image/png;base64,'.base64_encode($png.$variant);
}

it('saves a dark logo, background and accent', function (): void {
    lynx()->run(SaveBranding::class, ['background_dark' => '#101820', 'accent_dark' => '#F2A65A', 'logo_dark' => darkModePng('dark')]);
    $branding = Branding::current();

    expect($branding->darkBackground)->toBe('#101820')
        ->and($branding->darkAccent)->toBe('#f2a65a')
        ->and($branding->uploadedDarkLogo)->toMatch(Branding::LOGO_FILE)
        ->and(Storage::disk('local')->exists('lynx-admin/'.$branding->uploadedDarkLogo))->toBeTrue();
    $this->get((string) $branding->darkLogoUrl())->assertOk();
});

it('uses the light branding in dark mode until dark values are set, with the accent made readable there', function (): void {
    lynx()->run(SaveBranding::class, ['accent' => '#b4602c', 'background' => '#f3eee4']);
    $css = Branding::current()->themeCss();

    expect($css)->toContain('html:root{--primary: #b4602c !important;', 'body[data-lynx-backdrop]{background-color: #f3eee4;')
        ->toContain('html.dark:root{--primary: '.AccentContrast::onDark('#b4602c').' !important;')
        ->not->toContain('html.dark body');
});

it('gives dark mode its own accent and background, with readable text', function (): void {
    lynx()->run(SaveBranding::class, ['accent' => '#b4602c', 'accent_dark' => '#f2a65a', 'background_dark' => '#101820']);
    $css = Branding::current()->themeCss();

    expect($css)->toContain('html.dark:root{--primary: #f2a65a !important; --primary-foreground: #111111 !important;')
        ->toContain('html.dark body[data-lynx-backdrop]{background-color: #101820; color: #ffffff;');
});

it('puts the colours in a style element, not on the html element', function (): void {
    lynx()->run(SaveBranding::class, ['accent' => '#b4602c', 'accent_dark' => '#f2a65a']);

    $this->get('/lynx/login')->assertOk()
        ->assertSee('<style data-lynx-branding>', false)
        ->assertDontSee('style="--primary', false)
        ->assertSee('data-lynx-backdrop', false);
});

it('shows each logo in its mode', function (): void {
    lynx()->run(SaveBranding::class, ['logo' => darkModePng(), 'logo_dark' => darkModePng('dark')]);
    $branding = Branding::current();

    $this->get('/lynx/login')->assertOk()
        ->assertSee('class="lynx-logo-light', false)
        ->assertSee('class="lynx-logo-dark', false)
        ->assertSee((string) $branding->darkLogoUrl(), false);
    expect($branding->themeCss())->toContain('html.dark .lynx-logo-light{display:none !important}');
});

it('keeps a logo file the other mode still uses', function (): void {
    lynx()->run(SaveBranding::class, ['logo' => darkModePng(), 'logo_dark' => darkModePng()]);
    $file = Branding::current()->uploadedLogo;

    lynx()->run(SaveBranding::class, ['remove_logo_dark' => true]);

    expect(Branding::current()->uploadedDarkLogo)->toBeNull()
        ->and(Storage::disk('local')->exists('lynx-admin/'.$file))->toBeTrue();
});

it('reads dark defaults from the config', function (): void {
    config(['lynx-admin.branding.accent_dark' => '#336699', 'lynx-admin.branding.logo_dark' => 'img/logo-white.svg']);
    $branding = Branding::current();

    expect($branding->darkAccent)->toBe('#336699')
        ->and($branding->darkLogoUrl())->toBe(asset('img/logo-white.svg'));
});

it('edits the dark branding in Settings, with a dark preview', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(BrandingForm::class)
        ->assertSee('data-preview="dark"', false)
        ->set('accentDark', '#F2A65A')
        ->set('backgroundDark', '#101820')
        ->call('save')
        ->assertHasNoErrors();

    expect(Branding::current()->darkAccent)->toBe('#f2a65a')
        ->and(Branding::current()->darkBackground)->toBe('#101820');
});

it('refuses a dark colour that is not hex', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(BrandingForm::class)->set('accentDark', 'orange')->call('save')->assertHasErrors('accentDark');
});
