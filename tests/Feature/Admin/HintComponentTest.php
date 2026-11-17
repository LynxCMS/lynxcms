<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
 * <x-lynx-admin::hint>: a help icon beside a heading or a label that explains it on hover,
 * keyboard focus or a tap. The icon is a button named by $label, described by the hint's text.
 */

it('draws a named button with the hint\'s text', function (): void {
    $html = Blade::render('<x-lynx-admin::hint label="How names fill again" data-names-help>Clear a name to fill it again.</x-lynx-admin::hint>');

    expect($html)->toContain('aria-label="How names fill again"')
        ->toContain('<button type="button"')
        ->toContain('role="tooltip"')
        ->toContain('Clear a name to fill it again.')
        ->toContain('data-names-help');
});

it('opens below the icon unless told another side', function (): void {
    expect(Blade::render('<x-lynx-admin::hint label="Why">Because.</x-lynx-admin::hint>'))->toContain('data-side="bottom"')
        ->and(Blade::render('<x-lynx-admin::hint label="Why" side="right">Because.</x-lynx-admin::hint>'))->toContain('data-side="right"');
});

it('escapes the label', function (): void {
    expect(Blade::render('<x-lynx-admin::hint :label="$label">Text.</x-lynx-admin::hint>', ['label' => '"><script>']))
        ->not->toContain('<script>');
});
