<?php

declare(strict_types=1);

namespace LynxCms\Sitemap;

use Carbon\CarbonImmutable;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use XMLWriter;

/**
 * Writes sitemap XML: a urlset with one <url> per page and language and the page's other
 * languages as xhtml:link alternates, or a sitemapindex of parts.
 *
 * @internal
 */
final readonly class SitemapWriter
{
    private const string NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    private const string XHTML = 'http://www.w3.org/1999/xhtml';

    public function __construct(private SitemapFilters $filters) {}

    /** @param list<Page> $pages */
    public function urlset(array $pages): string
    {
        $xml = $this->start('urlset');
        $xml->writeAttribute('xmlns:xhtml', self::XHTML);

        foreach ($pages as $page) {
            $this->page($xml, $page);
        }

        return $this->finish($xml);
    }

    /** @param list<string> $urls absolute URLs of the parts */
    public function index(array $urls): string
    {
        $xml = $this->start('sitemapindex');

        foreach ($urls as $url) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $url);
            $xml->endElement();
        }

        return $this->finish($xml);
    }

    private function page(XMLWriter $xml, Page $page): void
    {
        $urls = $this->urls($page);
        $alternates = count($urls) > 1 ? [...$urls, 'x-default' => $this->absolute($page->localUrl(lynx()->languages->default()))] : [];
        $lastmod = $page->updatedAt === null ? null : CarbonImmutable::parse($page->updatedAt)->toAtomString();

        foreach ($urls as $tag => $url) {
            if ($this->filters->excludes($page, $this->languageOf($tag))) {
                continue;
            }

            $xml->startElement('url');
            $xml->writeElement('loc', $url);

            if ($lastmod !== null) {
                $xml->writeElement('lastmod', $lastmod);
            }

            foreach ($alternates as $hreflang => $href) {
                $xml->startElement('xhtml:link');
                $xml->writeAttribute('rel', 'alternate');
                $xml->writeAttribute('hreflang', (string) $hreflang);
                $xml->writeAttribute('href', $href);
                $xml->endElement();
            }

            $xml->endElement();
        }
    }

    /**
     * The page's URL in each active language it has one in; the default language always.
     *
     * @return array<string, string> hreflang tag => absolute URL
     */
    private function urls(Page $page): array
    {
        $default = lynx()->languages->default();
        $urls = [];

        foreach (lynx()->languages->active() as $language) {
            if ($language->id === $default->id || ($page->isActiveIn($language) && $page->path($language) !== null)) {
                $urls[$this->tag($language)] = $this->absolute($page->localUrl($language));
            }
        }

        return $urls;
    }

    private function languageOf(string $tag): Language
    {
        return lynx()->languages->active()->first(fn (Language $language): bool => $this->tag($language) === $tag) ?? lynx()->languages->default();
    }

    private function tag(Language $language): string
    {
        return str_replace('_', '-', $language->appLocale());
    }

    private function absolute(string $path): string
    {
        return rtrim(url('/'), '/').$path;
    }

    private function start(string $root): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($root);
        $xml->writeAttribute('xmlns', self::NAMESPACE);

        return $xml;
    }

    private function finish(XMLWriter $xml): string
    {
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }
}
