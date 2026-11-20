<?php

declare(strict_types=1);

use LynxCms\Admin\Branding\AccentContrast;
use LynxCms\Admin\Branding\Branding;

/*
 * A site that sets an accent for light mode only gets a dark mode accent that stays readable on the
 * dark background (WCAG 1.4.3, 4.5:1), so links and focus rings in that colour can be read.
 */

it('lightens an accent until it reads on the dark background', function (): void {
    $dark = AccentContrast::onDark('#005f78');

    expect(AccentContrast::ratio($dark, AccentContrast::DARK_SURFACE))->toBeGreaterThanOrEqual(4.5)
        ->and($dark)->not->toBe('#005f78');
});

it('keeps an accent that already reads on the dark background', function (): void {
    expect(AccentContrast::onDark('#7dd3fc'))->toBe('#7dd3fc');
});

it('gives dark mode the readable accent, and light mode the site\'s own', function (): void {
    $css = Branding::from(['accent' => '#005f78'], [])->themeCss();

    expect($css)->toContain('html:root{--primary: #005f78')
        ->and($css)->toContain('html.dark:root{--primary: '.AccentContrast::onDark('#005f78'));
});

it('keeps the dark accent a site sets itself', function (): void {
    expect(Branding::from(['accent' => '#005f78', 'accent_dark' => '#123456'], [])->themeCss())->toContain('html.dark:root{--primary: #123456');
});
