<?php

declare(strict_types=1);

namespace LynxCms\Sitemap;

/**
 * The sitemap's files, built once and cached: /sitemap.xml (one urlset, or an index of parts)
 * and each part. lynx()->sitemap() returns it.
 *
 * @api
 */
final readonly class Sitemap
{
    public function __construct(
        private SitemapPages $pages,
        private SitemapWriter $writer,
        private SitemapCache $cache,
        private SitemapSettings $settings,
    ) {}

    /** The body of /sitemap.xml. */
    public function root(): string
    {
        return $this->cache->remember($this->settings->fingerprint().'.root', function (): string {
            $parts = $this->parts();

            if ($this->settings->groupBy() === SitemapSchema::GROUP_NONE && count($parts) <= 1) {
                return $this->writer->urlset($this->pages->part(SitemapPages::ALL, 1) ?? []);
            }

            return $this->writer->index(array_map(fn (string $path): string => rtrim(url('/'), '/').'/'.$path, $parts));
        });
    }

    /**
     * The body of one part, or null when there is no such part in the grouping in use.
     *
     * @param  string  $group  SitemapPages::ALL, or a template name when grouped by template
     */
    public function part(string $group, int $part): ?string
    {
        $isGrouped = $this->settings->groupBy() === SitemapSchema::GROUP_TEMPLATE;

        if ($isGrouped === ($group === SitemapPages::ALL) || $part < 1 || $part > $this->pages->partsOf($group)) {
            return null;
        }

        return $this->cache->remember($this->settings->fingerprint().'.'.SitemapPages::path($group, $part), fn (): string => $this->writer->urlset($this->pages->part($group, $part) ?? []));
    }

    /**
     * Every part's file name, e.g. ["sitemap-1.xml", "sitemap-2.xml"].
     *
     * @return list<string>
     */
    public function parts(): array
    {
        $paths = [];

        foreach (array_keys($this->pages->groups()) as $group) {
            for ($part = 1; $part <= $this->pages->partsOf((string) $group); $part++) {
                $paths[] = SitemapPages::path((string) $group, $part);
            }
        }

        return $paths;
    }

    /** Pages listed, before filters such as noindex. */
    public function pageCount(): int
    {
        return array_sum($this->pages->groups());
    }
}
