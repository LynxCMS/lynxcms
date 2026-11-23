<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\Publication;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Support\Value;

/**
 * Links in rich text that name a page by id (<a href="/courses/alpha/" data-page="42">, ADR-0145).
 * On output each gets the page's current address in the language shown, keeping a section
 * ("#fees"). Visitors get only the link's text when they could not open the page: unpublished, in
 * the trash, gone, or hidden from them by the visibility rules, as page fields do.
 *
 * @internal
 */
final readonly class PageLinks
{
    public const string ATTRIBUTE = 'data-page';

    public function __construct(
        private PageLoader $loader,
        private PageVisibility $visibility,
        private Trash $trash,
        private Publication $publication,
    ) {}

    /**
     * The page ids links in the HTML name, in order, once each.
     *
     * @return list<int>
     */
    public static function ids(string $html): array
    {
        if (! str_contains($html, self::ATTRIBUTE.'=')) {
            return [];
        }

        preg_match_all('/<a\s[^>]*\b'.self::ATTRIBUTE.'="([1-9][0-9]*)"/i', $html, $matches);

        return array_values(array_unique(array_map(intval(...), $matches[1])));
    }

    /** Who reads the output, which changes what links resolve to: "visitor" or "admin". */
    public function audience(): string
    {
        return $this->visibility->servesVisitors() ? 'visitor' : 'admin';
    }

    public function resolve(string $html, Language $language): string
    {
        $ids = self::ids($html);

        if ($ids === []) {
            return $html;
        }

        $pages = $this->pages($ids);
        $forVisitors = $this->visibility->servesVisitors();

        return preg_replace_callback('/<a\s([^>]*)>(.*?)<\/a>/is', function (array $link) use ($pages, $forVisitors, $language): string {
            if (preg_match('/\b'.self::ATTRIBUTE.'="([0-9]+)"/', $link[1], $id) !== 1) {
                return $link[0];
            }

            $page = $pages[(int) $id[1]] ?? null;

            if ($page === null) {
                return $forVisitors ? $link[2] : $link[0];
            }

            return $page->template()->hasUrl() ? '<a '.$this->withHref($link[1], $page->localUrl($language)).'>'.$link[2].'</a>' : $link[0];
        }, $html) ?? $html;
    }

    /** The attributes with href set to the address, keeping the section the stored href named. */
    private function withHref(string $attributes, string $url): string
    {
        $section = preg_match('/\bhref="[^"#]*(#[^"]*)"/', $attributes, $match) === 1 ? $match[1] : '';
        $href = 'href="'.e($url).$section.'"';
        $count = 0;
        $replaced = preg_replace('/\bhref="[^"]*"/', $href, $attributes, 1, $count);

        return $count > 0 && $replaced !== null ? $replaced : $href.' '.$attributes;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, Page> the pages the reader may open, by id
     */
    private function pages(array $ids): array
    {
        $pages = $this->visibility->only($this->loader->many($ids))->all();

        if ($this->visibility->servesVisitors()) {
            $trashed = $this->trash->heldAmong(array_map(fn (Page $page): int => (int) $page->id, $pages));
            $pages = $this->publication->published(array_values(array_filter($pages, fn (Page $page): bool => ! in_array((int) $page->id, $trashed, true))));
        }

        $byId = [];

        foreach ($pages as $page) {
            $byId[Value::int($page->id)] = $page;
        }

        return $byId;
    }
}
