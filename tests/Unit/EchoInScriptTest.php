<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * A value inside an Alpine or Livewire expression (x-on:click, :class, wire:click, @click…) goes
 * through @js() or Js::from(). Between quotes, '{{ $name }}' is escaped for HTML only: the browser
 * undoes that before the expression runs as JavaScript, so a quote or a line break in the value
 * would end the string. Blade compiles no @js inside a component tag, where Js::from() is used.
 */

/** An attribute whose value runs as JavaScript, holding '{{ … }}' between quotes. */
const QUOTED_ECHO_IN_SCRIPT = '/(?:x-[\w.:-]+|wire:[\w.:-]+|@[\w.:-]+|\s:[\w-]+)="[^"]*\'\{\{[^}]*\}\}\'/';

it('passes values into Alpine and Livewire expressions through @js or Js::from', function (): void {
    $packages = dirname(__DIR__, 2).'/packages';
    $found = [];

    foreach (Finder::create()->files()->name('*.blade.php')->in($packages.'/*/resources/views') as $file) {
        $blade = $file->getContents();
        preg_match_all(QUOTED_ECHO_IN_SCRIPT, $blade, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$attribute, $offset]) {
            $found[] = substr($file->getPathname(), strlen($packages) + 1).':'.(substr_count(substr($blade, 0, $offset), "\n") + 1);
        }
    }

    expect($found)->toBe([]);
});
