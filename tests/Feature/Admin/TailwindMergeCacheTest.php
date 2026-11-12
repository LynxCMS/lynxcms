<?php

declare(strict_types=1);

use LynxCms\Admin\View\TailwindMergeMacros;

/*
 * Merged class strings are kept for the life of the process, which under Octane is many requests,
 * so the cache keeps a bounded number and drops the oldest first.
 */

it('merges later classes over earlier ones', function (): void {
    expect(TailwindMergeMacros::merge(['px-2 py-1'], 'px-4'))->toBe('py-1 px-4');
});

it('keeps a bounded number of merged strings, dropping the oldest first', function (): void {
    TailwindMergeMacros::forget();

    foreach (range(1, TailwindMergeMacros::MAX_CACHED + 25) as $width) {
        TailwindMergeMacros::merge(['p-1'], "w-[{$width}px]");
    }

    expect(TailwindMergeMacros::cached())->toBe(TailwindMergeMacros::MAX_CACHED);
});
