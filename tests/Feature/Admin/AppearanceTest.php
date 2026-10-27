<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use LynxCms\Admin\Appearance;
use LynxCms\Core\Lynx;

beforeEach(fn (): Lynx => courseSite());

function adminHtml(): string
{
    return (string) test()->actingAs(new GenericUser(['id' => 1]))->get('/lynx/pages')->getContent();
}

it('offers every option of BlatUI\'s customizer in the config', function (): void {
    expect(array_keys((array) config('lynx-admin.appearance')))
        ->toBe(['base', 'theme', 'radius', 'font', 'heading_font', 'shadow', 'spacing', 'tracking', 'input_style', 'load_fonts']);
});

it('renders every chosen option on <html>', function (): void {
    config(['lynx-admin.appearance' => [
        'base' => 'stone', 'theme' => 'emerald', 'radius' => '1', 'font' => 'inter', 'heading_font' => 'lora',
        'shadow' => 'lg', 'spacing' => 'compact', 'tracking' => 'wide', 'input_style' => 'fill',
    ]]);

    expect(adminHtml())->toContain('data-base="stone" data-theme="emerald" data-radius="1" data-font="inter" data-font-heading="lora" data-shadow="lg" data-spacing="compact" data-tracking="wide" data-input-style="fill"');
});

it('leaves out values BlatUI does not know', function (): void {
    expect(Appearance::attributes(['base' => 'purple', 'theme' => 'blue', 'radius' => '0.4', 'font' => 'comic-sans']))
        ->toBe(['data-theme' => 'blue']);
});

it('loads the web fonts the chosen fonts need, once each', function (): void {
    expect(Appearance::fontsUrl(['font' => 'inter', 'heading_font' => 'source-serif']))
        ->toBe('https://fonts.bunny.net/css?family=inter:400,500,600,700|source-serif-4:400,500,600,700&display=swap')
        ->and(Appearance::fontsUrl(['font' => 'jakarta', 'heading_font' => 'jakarta']))
        ->toBe('https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap');
});

it('loads BlatUI\'s default font when none is chosen, and nothing for system fonts', function (): void {
    expect(Appearance::fontsUrl([]))->toContain('family=instrument-sans:')
        ->and(Appearance::fontsUrl(['font' => 'system', 'heading_font' => 'mono']))->toBeNull();
});

it('sets the admin in Geist by default, headings included', function (): void {
    expect(adminHtml())->toContain('data-font="geist"')
        ->and(adminHtml())->toContain('data-font-heading="sans"')
        ->and(adminHtml())->toContain('family=geist:');
});

it('lets the config give headings their own font', function (): void {
    config(['lynx-admin.appearance.heading_font' => 'mono']);

    expect(adminHtml())->toContain('data-font-heading="mono"');
});

it('links the fonts stylesheet unless loading fonts is turned off', function (): void {
    config(['lynx-admin.appearance.font' => 'geist']);
    expect(adminHtml())->toContain('href="https://fonts.bunny.net/css?family=geist:400,500,600,700&amp;display=swap"');

    config(['lynx-admin.appearance.load_fonts' => false]);
    expect(adminHtml())->not->toContain('fonts.bunny.net');
});
