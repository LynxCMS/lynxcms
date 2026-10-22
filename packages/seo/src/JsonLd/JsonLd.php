<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

use Closure;
use LynxCms\Core\Fields\Choices\Choice;
use LynxCms\Core\Pages\Page;
use LynxCms\Seo\SeoData;
use LynxCms\Seo\SeoSchema;
use LynxCms\Seo\Support\AbsoluteUrl;
use LynxCms\Seo\Support\PageText;

/**
 * A page's JSON-LD graph: the site (WebSite, its Organization or Person), the page (WebPage,
 * BreadcrumbList), the entity its template is mapped to, and what extensions add. Developers
 * extend it in a service provider: app(JsonLd::class)->extend(fn (Page $page, JsonLdGraph $graph) => ...).
 *
 * @api
 */
final class JsonLd
{
    /** @var list<Closure(Page, JsonLdGraph): mixed> */
    private array $extensions = [];

    public function __construct(
        private readonly Mappings $mappings,
        private readonly EntityBuilder $entities,
        private readonly ValueConverter $converter,
        private readonly PageText $text,
    ) {}

    /** @param  Closure(Page, JsonLdGraph): mixed  $extension */
    public function extend(Closure $extension): void
    {
        $this->extensions[] = $extension;
    }

    /** @return array{'@context': string, '@graph': list<array<string, mixed>>} */
    public function build(Page $page, SeoData $seo): array
    {
        $graph = new JsonLdGraph;
        $organization = $this->organization($seo);
        $graph->add($this->website($seo, $organization));

        if ($organization !== null) {
            $graph->add($organization);
        }

        $mapping = $this->mappings->for($page->template()->name);
        $entity = $mapping === null ? null : $this->entities->build($page, $mapping, $graph);

        if ($entity !== null && $organization !== null && in_array($entity['@type'], SchemaTypes::LOCAL_BUSINESSES, true)) {
            $entity['parentOrganization'] = ['@id' => $organization['@id']];
        }

        $crumbs = $this->breadcrumbs($page);
        $graph->add($this->webPage($page, $seo, $crumbs !== null, $entity));
        array_map($graph->add(...), array_values(array_filter([$crumbs, $entity])));

        foreach ($this->extensions as $extension) {
            $extension($page, $graph);
        }

        return $graph->toArray();
    }

    /** Whether the page's template is mapped to an article type (og:type article). */
    public function isArticle(Page $page): bool
    {
        return in_array($this->mappings->for($page->template()->name)?->type, SchemaTypes::ARTICLES, true);
    }

    /** The @id of the site's organization or person, when the settings name one. */
    public static function organizationId(): string
    {
        return AbsoluteUrl::of('/').'#organization';
    }

    private function siteId(string $fragment): string
    {
        return AbsoluteUrl::of('/').'#'.$fragment;
    }

    /**
     * @param  array<string, mixed>|null  $organization
     * @return array<string, mixed>
     */
    private function website(SeoData $seo, ?array $organization): array
    {
        $home = lynx()->pages->get('/');

        return [
            '@type' => 'WebSite',
            '@id' => $this->siteId('website'),
            'url' => AbsoluteUrl::of($home?->localUrl(lynx()->languages->current()) ?? '/'),
            'name' => $seo->siteName,
            'inLanguage' => str_replace('_', '-', $seo->locale),
            'publisher' => $organization === null ? null : ['@id' => $organization['@id']],
        ];
    }

    /** @return array<string, mixed>|null the site's owner, when the settings name one */
    private function organization(SeoData $seo): ?array
    {
        $settings = lynx()->settings(SeoSchema::SETTINGS_TEMPLATE);
        $name = $this->text->of($settings, SeoSchema::ORG_NAME);
        $name = $name !== '' ? $name : $seo->siteName;

        if ($name === '' || $settings === null) {
            return null;
        }

        $type = $settings->templateField(SeoSchema::ORG_TYPE) === null ? null : $settings->get(SeoSchema::ORG_TYPE);
        $isPerson = $type instanceof Choice && $type->value === SeoSchema::ORG_PERSON;
        $logo = $settings->templateField(SeoSchema::ORG_LOGO) === null ? null : $this->converter->convert($settings->get(SeoSchema::ORG_LOGO), SchemaTypes::IMAGE, 'logo');

        return [
            '@type' => $isPerson ? 'Person' : 'Organization',
            '@id' => self::organizationId(),
            'name' => $name,
            'url' => AbsoluteUrl::of('/'),
            $isPerson ? 'image' : 'logo' => $logo,
            'sameAs' => $this->converter->convert($settings->getUnformatted(SeoSchema::ORG_SAME_AS), SchemaTypes::LINES, 'sameAs'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $entity
     * @return array<string, mixed>
     */
    private function webPage(Page $page, SeoData $seo, bool $hasCrumbs, ?array $entity): array
    {
        $url = AbsoluteUrl::of($page->localUrl(lynx()->languages->current()));

        return [
            '@type' => 'WebPage',
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $seo->title,
            'description' => $seo->description,
            'inLanguage' => str_replace('_', '-', $seo->locale),
            'isPartOf' => ['@id' => $this->siteId('website')],
            'breadcrumb' => $hasCrumbs ? ['@id' => $url.'#breadcrumb'] : null,
            'primaryImageOfPage' => $seo->image === null ? null : ['@type' => 'ImageObject', 'url' => $seo->image['url'], 'width' => $seo->image['width'], 'height' => $seo->image['height']],
            'dateModified' => $this->converter->convert($page->updatedAt, SchemaTypes::DATE, 'dateModified'),
            'mainEntity' => $entity === null ? null : ['@id' => $entity['@id']],
        ];
    }

    /** @return array<string, mixed>|null the path from the home page, when there is one */
    private function breadcrumbs(Page $page): ?array
    {
        $trail = [];

        for ($step = $page; $step !== null; $step = $step->parent()) {
            if ($step->template()->hasUrl()) {
                array_unshift($trail, $step);
            }
        }

        if (count($trail) < 2) {
            return null;
        }

        $items = array_map(fn (Page $step, int $index): array => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $this->text->of($step, 'title'),
            'item' => AbsoluteUrl::of($step->localUrl(lynx()->languages->current())),
        ], $trail, array_keys($trail));

        return ['@type' => 'BreadcrumbList', '@id' => AbsoluteUrl::of($page->localUrl(lynx()->languages->current())).'#breadcrumb', 'itemListElement' => $items];
    }
}
