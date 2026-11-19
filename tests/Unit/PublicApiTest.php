<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * The public API is explicit (AGENTS.md rule 10): every class says whether it is @api, a promise
 * from 1.0 on, or @internal. The guides' code is what apps copy, so it may use only @api classes
 * (documentation/reports/api-audit-2026-11.md). Pest's arch() can't read docblocks, hence files.
 */

/**
 * The marker of each class, interface, trait and enum in the packages, by fully qualified name.
 *
 * @return array<string, string> "api", "internal", "both" or "none"
 */
function publicApiMarkers(): array
{
    static $markers = null;

    if ($markers !== null) {
        return $markers;
    }

    $markers = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/packages/*/src')->name('*.php') as $file) {
        $declared = declaredClass($file->getContents());

        if ($declared !== null) {
            $markers[$declared['name']] = $declared['marker'];
        }
    }

    return $markers;
}

/** @return array{name: string, marker: string}|null */
function declaredClass(string $php): ?array
{
    if (preg_match('/^namespace\s+([\w\\\\]+);/m', $php, $namespace) !== 1
        || preg_match('/^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $php, $class, PREG_OFFSET_CAPTURE) !== 1) {
        return null;
    }

    $before = substr($php, 0, $class[0][1]);
    $docblock = preg_match('/\/\*\*((?:(?!\*\/).)*)\*\/\s*(?:#\[[^\]]*\]\s*)*$/s', $before, $doc) === 1 ? $doc[1] : '';
    $isApi = preg_match('/@api\b/', $docblock) === 1;
    $isInternal = preg_match('/@internal\b/', $docblock) === 1;

    return [
        'name' => $namespace[1].'\\'.$class[1][0],
        'marker' => match (true) {
            $isApi && $isInternal => 'both',
            $isApi => 'api',
            $isInternal => 'internal',
            default => 'none',
        },
    ];
}

it('marks every class of the packages either @api or @internal', function (): void {
    $unmarked = array_keys(array_filter(publicApiMarkers(), fn (string $marker): bool => in_array($marker, ['none', 'both'], true)));

    expect($unmarked)->toBe([]);
});

it('lets the guides\' code use only @api classes', function (): void {
    $markers = publicApiMarkers();
    $internal = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/docs/snippets')->name('*.php') as $file) {
        preg_match_all('/^use\s+(LynxCms\\\\[\w\\\\]+)(?:\s+as\s+\w+)?;/m', $file->getContents(), $imports);

        foreach ($imports[1] as $class) {
            if (($markers[$class] ?? 'api') !== 'api') {
                $internal[] = $file->getRelativePathname().': '.$class;
            }
        }
    }

    expect($internal)->toBe([]);
});
