<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LynxCms\Core\Query\SelectorParser;
use Symfony\Component\Finder\Finder;

/*
 * The documentation (docs/) stays true to the code: selectors in it parse, every field type has
 * a section on the Field Types page, and every lynx:* command is in the Artisan reference.
 */

const DOCS = __DIR__.'/../../../docs';

/** @return array<string, string> "file: selector" => selector */
function documentedSelectors(): array
{
    $selectors = [];
    $finder = Finder::create()->files()->in(DOCS)->name('*.md')->exclude('node_modules');

    foreach ($finder as $file) {
        preg_match_all("/->(?:find|findOne|count|children)\('([^'\$]+)'/", $file->getContents(), $matches);

        foreach ($matches[1] as $selector) {
            $selectors[$file->getRelativePathname().': '.$selector] = $selector;
        }
    }

    return $selectors;
}

it('documents selectors that parse', function (string $selector): void {
    expect(app(SelectorParser::class)->parse($selector))->toBeArray()->not->toBeEmpty();
})->with(documentedSelectors());

/** A heading's anchor, as VitePress makes it. */
function headingAnchor(string $heading): string
{
    $text = preg_replace('/[\x{0300}-\x{036F}]/u', '', (string) Normalizer::normalize(strip_tags(str_replace('`', '', $heading)), Normalizer::FORM_KD));
    $slug = trim((string) preg_replace('/-{2,}/', '-', (string) preg_replace('/[\s~`!@#$%^&*()\-_+=\[\]{}|\\\\;:"\'“”‘’<>,.?\/]+/u', '-', (string) $text)), '-');

    return mb_strtolower((string) preg_replace('/^(\d)/', '_$1', $slug));
}

/** @return array<string, array{string, string}> "file: link" => [page path, anchor] */
function documentedAnchors(): array
{
    $anchors = [];

    foreach (Finder::create()->files()->in(DOCS)->name('*.md')->exclude('node_modules') as $file) {
        $self = '/'.preg_replace('/(index)?\.md$/', '', $file->getRelativePathname());
        preg_match_all('/\]\((\/[^)#\s]*)?#([^)\s]+)\)/', $file->getContents(), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $anchors[$file->getRelativePathname().': '.$match[0]] = [$match[1] !== '' ? $match[1] : $self, $match[2]];
        }
    }

    return $anchors;
}

it('links to headings that exist', function (string $path, string $anchor): void {
    $file = DOCS.rtrim($path, '/').(str_ends_with($path, '/') ? '/index.md' : '.md');
    preg_match_all('/^#{2,4} (.+)$/m', (string) file_get_contents($file), $headings);

    expect(array_map(headingAnchor(...), $headings[1]))->toContain($anchor);
})->with(documentedAnchors());

it('documents every field type on the Field Types page', function (): void {
    $page = (string) file_get_contents(DOCS.'/basics/field-types.md');
    $missing = array_values(array_filter(
        array_keys(lynx()->fields->types()),
        fn (string $type): bool => ! str_contains($page, "### `{$type}`"),
    ));

    expect($missing)->toBe([]);
});

it('lists every lynx command in the Artisan reference', function (): void {
    $page = (string) file_get_contents(DOCS.'/reference/artisan.md');
    $missing = array_values(array_filter(
        array_keys(Artisan::all()),
        fn (string $name): bool => str_starts_with($name, 'lynx:') && ! str_contains($page, "`{$name}"),
    ));

    expect($missing)->toBe([]);
});
