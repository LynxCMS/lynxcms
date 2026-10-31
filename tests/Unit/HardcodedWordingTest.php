<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Wording in views goes through __() (ADR-0097). Words typed into a Blade file show in English on a
 * Portuguese screen, and no other test would notice, since tests never assert wording. This one
 * reads every package's views for text between tags and in the attributes people read.
 */

/** Text that stays as it is in every language: code, examples and names. */
const WORDING_ALLOWED = [
    'Português',
    'SEO',
    'KB',
    '$page-&gt;',
    'format=onsite|hybrid',
    'price>0',
    'page, sort',
];

/** Attributes whose value people read, in plain HTML or as a component's prop. */
const READ_ATTRIBUTES = 'title|placeholder|aria-label|description|label|heading|tooltip|wire:confirm';

/** @return list<string> what the view shows that doesn't come from a translation */
function hardcodedWordingIn(string $blade): array
{
    $view = (string) preg_replace([
        '/\{\{--.*?--\}\}/s',
        '/^@props\(\[.*?^\]\)/ms',
        '/<(\/?)\{\{.*?\}\}/',
        '/<(script|style|code|pre)\b.*?<\/\1>/s',
        '/@php\b(?!\().*?@endphp/s',
        '/\{\{.*?\}\}|\{!!.*?!!\}/s',
    ], ['', '', '<$1x', '', '', ''], $blade);
    preg_match_all('/(?<![:\w-])(?:'.READ_ATTRIBUTES.')="([^"]*)"/', $view, $attributes);

    $text = (string) preg_replace([
        '/@\w+\s*(\((?:[^()\'"]++|\'[^\']*\'|"[^"]*"|(?1))*\))?/',
        '/<[a-zA-Z\/!](?:[^"\'>]|"[^"]*"|\'[^\']*\')*>/s',
    ], "\n", $view);

    $found = [...$attributes[1], ...array_map(trim(...), explode("\n", $text))];

    return array_values(array_filter($found, fn (string $line): bool => preg_match('/\p{L}{2}/u', $line) === 1 && ! in_array($line, WORDING_ALLOWED, true)));
}

/** @return array<string, array{string}> */
function packageViews(): array
{
    $views = [];

    foreach (Finder::create()->files()->name('*.blade.php')->in(dirname(__DIR__, 2).'/packages/*/resources/views') as $file) {
        $views[$file->getRelativePathname().' in '.$file->getPath()] = [$file->getPathname()];
    }

    return $views;
}

it('finds wording typed into a view', function (): void {
    $view = <<<'BLADE'
        <x-button title="Save it" :title="$bound" class="w-full">Hard wording</x-button>
        BLADE;

    expect(hardcodedWordingIn($view))->toBe(['Save it', 'Hard wording']);
});

it('leaves translations, code and comments alone', function (): void {
    $view = <<<'BLADE'
        {{-- A comment. --}}
        @if ($isShown)
            <p title="{{ __('a.title') }}">{{ __('a.line') }} <code>php artisan</code></p>
        @endif
        BLADE;

    expect(hardcodedWordingIn($view))->toBe([]);
});

it('has no wording typed into a package\'s views', function (string $path): void {
    expect(hardcodedWordingIn((string) file_get_contents($path)))->toBe([]);
})->with(packageViews());

it('has no notices typed into a package\'s code', function (): void {
    $typed = [];

    foreach (Finder::create()->files()->name('*.php')->in(dirname(__DIR__, 2).'/packages/*/src') as $file) {
        if (preg_match('/\$this->(notify\(|status = )[\'"]/', $file->getContents()) === 1) {
            $typed[] = $file->getRelativePathname();
        }
    }

    expect($typed)->toBe([]);
});
