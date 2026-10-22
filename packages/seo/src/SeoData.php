<?php

declare(strict_types=1);

namespace LynxCms\Seo;

/**
 * Everything a page's <head> says to search engines and social networks, in one language.
 * Built by SeoResolver; read it with lynx()->seo($page) for a custom head.
 *
 * @api
 */
final readonly class SeoData
{
    /**
     * @param  string|null  $robots  e.g. "noindex, follow"; null lets robots index the page
     * @param  array{url: string, width: int, height: int, alt: string}|null  $image  absolute URL
     * @param  list<string>  $localeAlternates  the page's other languages, e.g. ["en_US"]
     * @param  list<array{hreflang: string, href: string}>  $alternates  hreflang links, absolute
     * @param  string|null  $twitter  the site's X (Twitter) account, e.g. "@lynxcms"
     * @param  array<string, mixed>  $jsonLd  the JSON-LD document: @context and @graph
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public ?string $robots,
        public ?array $image,
        public string $siteName,
        public string $locale,
        public array $localeAlternates,
        public array $alternates,
        public ?string $twitter,
        public string $type,
        public array $jsonLd = [],
    ) {}

    /** @param array<string, mixed> $jsonLd */
    public function withJsonLd(array $jsonLd): self
    {
        return new self($this->title, $this->description, $this->canonical, $this->robots, $this->image, $this->siteName, $this->locale, $this->localeAlternates, $this->alternates, $this->twitter, $this->type, $jsonLd);
    }
}
