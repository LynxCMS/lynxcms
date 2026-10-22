<?php

declare(strict_types=1);

namespace LynxCms\Sitemap;

use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;

/**
 * Which pages the sitemap lists and how they split into parts: published pages of templates with
 * URLs, oldest first, in groups (all pages, or one per template) cut into parts of a fixed number
 * of pages. A part holds as many pages as fit the URL limit once every language is counted, so
 * parts are found by offset without reading the pages before them.
 *
 * @internal
 */
final readonly class SitemapPages
{
    /** The group of every page when the sitemap is not grouped by template. */
    public const string ALL = '';

    public function __construct(private SitemapSettings $settings) {}

    /** Pages per part: every page has a URL per active language at most. */
    public function perPart(): int
    {
        return max(1, intdiv($this->settings->perFile(), max(1, lynx()->languages->active()->count())));
    }

    /**
     * Groups that have pages: group => page count.
     *
     * @return array<string, int>
     */
    public function groups(): array
    {
        $templates = $this->templates();

        if ($this->settings->groupBy() === SitemapSchema::GROUP_NONE) {
            return [self::ALL => $templates === [] ? 0 : lynx()->pages->count($this->selector($templates))];
        }

        $groups = [];

        foreach ($templates as $template) {
            $count = lynx()->pages->count($this->selector([$template]));

            if ($count > 0) {
                $groups[$template] = $count;
            }
        }

        return $groups;
    }

    /** @return int parts of a group, 0 for an unknown one */
    public function partsOf(string $group): int
    {
        return (int) ceil(($this->groups()[$group] ?? 0) / $this->perPart());
    }

    /**
     * The pages of one part, or null when there is no such part.
     *
     * @return list<Page>|null
     */
    public function part(string $group, int $part): ?array
    {
        if ($part < 1 || $part > $this->partsOf($group)) {
            return null;
        }

        $templates = $group === self::ALL ? $this->templates() : [$group];
        $selector = $this->selector($templates).', start='.(($part - 1) * $this->perPart()).', limit='.$this->perPart();

        return array_values(array_filter(lynx()->pages->find($selector)->all(), fn (mixed $page): bool => $page instanceof Page));
    }

    /** The part's file, e.g. "sitemap-2.xml" or "sitemap-event-2.xml". */
    public static function path(string $group, int $part): string
    {
        return $group === self::ALL ? "sitemap-{$part}.xml" : "sitemap-{$group}-{$part}.xml";
    }

    /** @return list<string> templates with URLs that are not left out, by name */
    private function templates(): array
    {
        $excluded = $this->settings->excluded();
        $names = lynx()->templates->all()
            ->filter(fn (Template $template): bool => $template->hasUrl() && ! in_array($template->name, $excluded, true))
            ->pluck('name')->sort()->values()->all();

        return array_values(array_filter($names, is_string(...)));
    }

    /** @param list<string> $templates */
    private function selector(array $templates): string
    {
        return 'template='.implode('|', $templates).', sort=id'.($this->settings->includesHidden() ? ', include=hidden' : '');
    }
}
