<?php

declare(strict_types=1);

namespace LynxCms\Seo;

use InvalidArgumentException;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;
use LynxCms\Core\View\HreflangBuilder;
use LynxCms\Seo\JsonLd\JsonLd;
use LynxCms\Seo\Support\AbsoluteUrl;
use LynxCms\Seo\Support\PageText;
use LynxCms\Seo\Support\ShareImage;

/**
 * Works out a page's SEO in the current language: its own SEO fields first, then its content,
 * then the site-wide settings (ADR-0055).
 *
 * @api
 */
final readonly class SeoResolver
{
    private const string NOINDEX = 'noindex, follow';

    /** What a template or controller may override, e.g. robots on a filtered list. */
    public const array OVERRIDES = ['title', 'description', 'canonical', 'robots'];

    public function __construct(
        private PageText $text,
        private ShareImage $images,
        private HreflangBuilder $hreflang,
        private JsonLd $jsonLd,
    ) {}

    /**
     * @param  array<string, string>  $overrides  title, description, canonical or robots ("" for robots lets the page be indexed)
     */
    public function resolve(Page $page, array $overrides = []): SeoData
    {
        $this->checkOverrides($overrides);
        $settings = lynx()->settings(SeoSchema::SETTINGS_TEMPLATE);
        $siteName = $this->text->of($settings, SeoSchema::SITE_NAME);
        $alternates = $this->hreflang->links($page);
        $locale = $this->locale(lynx()->languages->current()->appLocale());

        $seo = new SeoData(
            title: isset($overrides['title']) ? $this->text->plain($overrides['title']) : $this->title($page, $settings, $siteName),
            description: isset($overrides['description']) ? $this->excerpt($overrides['description']) : $this->description($page, $settings),
            canonical: isset($overrides['canonical']) ? AbsoluteUrl::of($overrides['canonical']) : $this->canonical($page),
            robots: array_key_exists('robots', $overrides) ? (trim($overrides['robots']) === '' ? null : $overrides['robots']) : ($this->isIndexable($page) ? null : self::NOINDEX),
            image: $this->images->first([[$page, SeoSchema::IMAGE], ...$this->imageFields($page), [$settings, SeoSchema::DEFAULT_IMAGE]]),
            siteName: $siteName,
            locale: $locale,
            localeAlternates: $this->otherLocales($alternates, $locale),
            alternates: $alternates,
            twitter: $this->twitter($settings),
            type: $this->jsonLd->isArticle($page) ? 'article' : 'website',
        );

        return $seo->withJsonLd($this->jsonLd->build($page, $seo));
    }

    /** @param array<string, string> $overrides */
    private function checkOverrides(array $overrides): void
    {
        foreach (array_keys($overrides) as $name) {
            if (! in_array($name, self::OVERRIDES, true)) {
                throw new InvalidArgumentException("Unknown SEO override [{$name}]: use ".implode(', ', self::OVERRIDES).'.');
            }
        }
    }

    private function excerpt(string $text): string
    {
        return $this->text->excerpt($this->text->plain($text), Value::int(config('lynx-seo.description_length'), 160));
    }

    private function title(Page $page, ?Page $settings, string $siteName): string
    {
        $own = $this->text->of($page, SeoSchema::TITLE);
        $title = $this->text->of($page, 'title');

        if ($own !== '' || $siteName === '') {
            return $own !== '' ? $own : $title;
        }

        $pattern = $this->text->of($settings, SeoSchema::TITLE_PATTERN);
        $pattern = $pattern !== '' ? $pattern : Value::string(config('lynx-seo.title_pattern'), '{title} | {site}');

        return strtr($pattern, ['{title}' => $title, '{site}' => $siteName]);
    }

    private function description(Page $page, ?Page $settings): string
    {
        $fields = Value::strings(config('lynx-seo.description_fields', []));
        $text = $this->text->first($page, SeoSchema::DESCRIPTION, ...$fields);
        $text = $text !== '' ? $text : $this->text->of($settings, SeoSchema::DEFAULT_DESCRIPTION);

        return $this->excerpt($text);
    }

    private function canonical(Page $page): string
    {
        $own = $this->text->of($page, SeoSchema::CANONICAL);

        return AbsoluteUrl::of($own !== '' ? $own : $page->localUrl(lynx()->languages->current()));
    }

    private function isIndexable(Page $page): bool
    {
        $isMarked = $page->templateField(SeoSchema::NOINDEX) !== null && Value::bool($page->getUnformatted(SeoSchema::NOINDEX));
        $isHidden = $page->hasStatus(PageStatus::HIDDEN) && (bool) config('lynx-seo.noindex_hidden', true);
        $isBlocked = (bool) config('lynx-seo.block_non_production', true) && ! app()->isProduction();

        return ! $isMarked && ! $isHidden && ! $isBlocked;
    }

    /** @return list<array{0: Page, 1: string}> */
    private function imageFields(Page $page): array
    {
        return array_map(fn (string $field): array => [$page, $field], Value::strings(config('lynx-seo.image_fields', [])));
    }

    /**
     * @param  list<array{hreflang: string, href: string}>  $alternates
     * @return list<string>
     */
    private function otherLocales(array $alternates, string $locale): array
    {
        $locales = array_map(fn (array $link): string => $this->locale($link['hreflang']), $alternates);

        return array_values(array_unique(array_diff($locales, [$locale, 'x_default'])));
    }

    private function twitter(?Page $settings): ?string
    {
        $handle = ltrim($this->text->of($settings, SeoSchema::TWITTER), '@');

        return $handle === '' ? null : '@'.$handle;
    }

    /** Open Graph writes locales as pt_PT, hreflang as pt-PT. */
    private function locale(string $tag): string
    {
        return str_replace('-', '_', $tag);
    }
}
