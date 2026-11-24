<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/*
 * VitePress checks that linked pages exist, but not the #anchor after them. Recipes and many
 * cross-links point at section headings, which break silently when a heading is renamed. This
 * builds each page's heading ids the way VitePress does and checks every internal link with an
 * anchor against them.
 */

/** VitePress's slugify (vitepress/dist/node, from @mdit-vue/shared). */
function docsSlug(string $heading): string
{
    $slug = Normalizer::normalize($heading, Normalizer::FORM_KD);
    $slug = is_string($slug) ? $slug : $heading;
    $slug = (string) preg_replace('/[\x{0300}-\x{036F}]/u', '', $slug);
    $slug = (string) preg_replace('/[\x{0000}-\x{001F}]/u', '', $slug);
    $slug = (string) preg_replace('/[\s~`!@#$%^&*()\-_+=[\]{}|\\\\;:"\'“”‘’<>,.?\/]+/u', '-', $slug);
    $slug = trim((string) preg_replace('/-{2,}/', '-', $slug), '-');

    return mb_strtolower((string) preg_replace('/^(\d)/', '_$1', $slug));
}

/** The text a heading renders, without Markdown syntax. */
function docsHeadingText(string $heading): string
{
    $text = (string) preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $heading);

    return trim(str_replace(['`', '**', '*'], '', $text));
}

/**
 * The ids a page's headings get, with VitePress's -1, -2 suffixes for repeated headings, plus
 * custom {#ids} and id attributes written in HTML.
 *
 * @return list<string>
 */
function docsAnchors(string $markdown): array
{
    $anchors = [];
    $inFence = false;

    foreach (explode("\n", $markdown) as $line) {
        if (preg_match('/^\s*(```|~~~)/', $line) === 1) {
            $inFence = ! $inFence;

            continue;
        }

        if ($inFence || preg_match('/^#{1,6}\s+(.+?)\s*$/', $line, $match) !== 1) {
            continue;
        }

        if (preg_match('/\{#([^}]+)\}$/', $match[1], $custom) === 1) {
            $anchors[] = $custom[1];

            continue;
        }

        $slug = docsSlug(docsHeadingText($match[1]));
        $count = count(array_filter($anchors, fn (string $anchor): bool => $anchor === $slug || preg_match('/^'.preg_quote($slug, '/').'-\d+$/', $anchor) === 1));
        $anchors[] = $count === 0 ? $slug : $slug.'-'.$count;
    }

    preg_match_all('/\bid="([^"]+)"/', $markdown, $ids);

    return [...$anchors, ...$ids[1]];
}

/** The Markdown file a docs link points at, or null when it isn't one. */
function docsTarget(string $docs, string $from, string $path): ?string
{
    $path = Str::before($path, '?');
    $base = $path === '' ? Str::beforeLast($from, '.md') : (str_starts_with($path, '/') ? $docs.$path : dirname($from).'/'.$path);
    $base = Str::chopEnd($base, ['.md', '.html']);

    foreach ([$base.'.md', rtrim($base, '/').'/index.md'] as $candidate) {
        if (is_file($candidate)) {
            return (string) realpath($candidate);
        }
    }

    return null;
}

it('links only to anchors that exist', function (): void {
    $docs = dirname(__DIR__, 2).'/docs';
    $files = Finder::create()->files()->in($docs)->name('*.md')->exclude(['node_modules', '.vitepress']);
    $broken = [];

    foreach ($files as $file) {
        $markdown = $file->getContents();
        preg_match_all('/\]\(((?:\/|\.{1,2}\/|#)[^)\s]*#[^)\s]+|#[^)\s]+)\)/', $markdown, $links);

        foreach ($links[1] as $link) {
            [$path, $anchor] = explode('#', $link, 2);
            $target = docsTarget($docs, $file->getRealPath(), $path);

            if ($target === null) {
                continue; // VitePress's own dead-link check covers missing pages.
            }

            if (! in_array(rawurldecode($anchor), docsAnchors((string) file_get_contents($target)), true)) {
                $broken[] = $file->getRelativePathname().': '.$link;
            }
        }
    }

    expect($broken)->toBe([]);
});

it('builds heading ids as VitePress does', function (string $heading, string $id): void {
    expect(docsSlug(docsHeadingText($heading)))->toBe($id);
})->with([
    ['Mail in the reader\'s language', 'mail-in-the-reader-s-language'],
    ['`$page` and `lynx()->pages`', 'page-and-lynx-pages'],
    ['Inside repeaters, blocks and files', 'inside-repeaters-blocks-and-files'],
    ['1. The data source', '_1-the-data-source'],
    ['[Finding Pages](/basics/selectors) in short', 'finding-pages-in-short'],
]);
