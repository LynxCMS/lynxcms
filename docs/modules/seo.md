---
title: SEO
description: Titles, descriptions, share images, canonical links, robots rules and structured data for every page.
order: 4
---

# SEO

SEO comes with `lynxcms/seo`. It writes the tags search engines and social networks read: the title, the description, the canonical link, the robots rule, Open Graph and X (Twitter) cards, the hreflang links and JSON-LD structured data. It also serves `/robots.txt`.

## Rendering

Put the component in your layout's `<head>`. It replaces `<title>` and `@hreflang`:

```blade
<head>
    <meta charset="utf-8">
    <x-lynx-seo :page="$page" />
</head>
```

`@seo($page)` renders the same tags. For a head of your own, `lynx()->seo($page)` returns a `SeoData` object with `title`, `description`, `canonical`, `robots`, `image`, `siteName`, `locale`, `localeAlternates`, `alternates`, `twitter` and `type`, all in the current language.

### Overrides for lists and filters

A template or controller can override the title, description, canonical link and robots rule for one response. A list filtered by query string, for example, should not be indexed and should point search engines at the unfiltered list:

```blade
<x-lynx-seo :page="$page" robots="noindex, follow" :canonical="$page->url" />
```

`@seo($page, ['robots' => 'noindex, follow'])` and `lynx()->seo($page, [...])` take the same keys: `title`, `description`, `canonical` and `robots`. A title given here is used as it is, without the title pattern. An empty `robots` lets the page be indexed even when it would otherwise say noindex. The JSON-LD uses the overridden title and description too.

To change the markup, publish the view:

```bash
php artisan vendor:publish --tag=lynx-seo-views
```

## Where the values come from

Each value is taken from the first place that has one:

| Tag | Page field | Then | Then |
| --- | --- | --- | --- |
| Title | `seo_title`, as written | The page title in the title pattern, e.g. `Risk management \| Lynx Academy` | The page title |
| Description | `seo_description` | The first of `description_fields` with text, tags stripped and cut at a word | The default description |
| Share image | `seo_image` | The first image of `image_fields` | The default share image |
| Canonical | `seo_canonical` (a full URL or a path) | The page's URL in the current language | |

Values follow the language chain, like any translatable field. Share images are cropped to 1200 by 630 pixels around their focus point and need `lynxcms/media`.

Pages ask robots not to index them (`noindex, follow`) when `seo_noindex` is on, when they are hidden (`noindex_hidden`), and on any site that is not in production (`block_non_production`). That last rule keeps a staging copy out of search results.

## SEO per template

The page fields are opt in. Turn SEO on for a template in the admin (**SEO**, then **Templates**) or with an action:

```php
lynx()->run(\LynxCms\Seo\Actions\EnableSeo::class, ['template' => 'article']);
```

The template gets `seo_title`, `seo_description`, `seo_image`, `seo_noindex` and `seo_canonical`. Pages that leave them empty use their content and the site settings, so pages of other templates get good tags too. Turning SEO off removes the fields from the template. Their values are kept, and come back when it is turned on again.

## Site settings

The site wide settings are a hidden page, edited in the page editor from **SEO**, then **Settings**:

- the site name and the title pattern (`{title}` and `{site}`), per language;
- the default description and the default share image;
- the X (Twitter) account;
- who runs the site, for structured data: an organization or a person, its name, logo and profiles elsewhere;
- extra lines for `robots.txt`.

Read them in templates like any settings page: `lynx()->settings('seo-settings')`.

## Structured data

The component also prints a JSON-LD graph that search engines read for rich results. Every page gets:

- the site (`WebSite`) and who runs it (`Organization` or `Person`, from the settings: owner name, logo, profiles elsewhere);
- the page (`WebPage`), with its title, description, language, share image and last change;
- its breadcrumbs (`BreadcrumbList`), from the home page down.

### Mapping a template to a type

On **SEO**, then **Structured data**, map a template to a schema.org type and say where each property comes from. A source is one of:

| Source | Example | Means |
| --- | --- | --- |
| A field | `startDate: starts` | The page's field, in the current language with fallback. |
| A built in | `headline: @title` | `@title`, `@url`, `@published` or `@modified`. |
| A fixed value | `offers.priceCurrency: =EUR` | The text after `=`. |
| The site's owner | `author: @organization` | The organization or person from the settings, linked by `@id`. Fits `author` and `publisher` on articles, `provider` on courses, `organizer` on events and `worksFor` on people. |

The types are Article, BlogPosting, NewsArticle, CreativeWork, Event, Product, Course, Place, LocalBusiness, Store, Restaurant, ProfessionalService, LodgingBusiness, Person and FAQPage. Values are converted for each property: dates to ISO 8601, images and links to full URLs, numbers to numbers, text without tags.

Dotted properties build nested nodes, so `address.streetAddress: street` gives a `PostalAddress` and `geo.latitude: lat` gives `GeoCoordinates`.

An event's venue is usually a page of its own. Point `location` at a page reference field: when the venue's template is mapped too (to `Place`), the venue is added to the graph once and the event links to it. Otherwise the event names the page and links to its URL.

A list property takes a repeater or matrix field, one node per enabled item, and its sub-properties read the item's subfields:

```
openingHoursSpecification: hours
openingHoursSpecification.dayOfWeek: day
openingHoursSpecification.opens: opens
```

The same works for questions and answers on a `FAQPage` (`mainEntity: faq`, `mainEntity.name: question`, `mainEntity.acceptedAnswer.text: answer`).

States such as `eventStatus` and `offers.availability` take a choice's value (`cancelled`, `in_stock`) or the schema.org name (`EventCancelled`). Shops and other local businesses point to the site's organization as `parentOrganization`, so branches share one brand.

A mapping that would make broken structured data is refused when saved, with the reason: an unknown property, a field the template lacks, or a field of the wrong kind, such as text for an image.

### In code

Map a template with an action, which is also a tool for MCP clients and the agent. Each property names its source, as in the admin, and the same checks refuse a mapping that would make broken structured data. It replaces the template's mapping and leaves the others:

```php
use LynxCms\Seo\Actions\MapTemplate;
use LynxCms\Seo\Actions\UnmapTemplate;

lynx()->run(MapTemplate::class, ['template' => 'event', 'type' => 'Event', 'map' => [
    'name' => '@title',
    'startDate' => 'starts',
    'location' => 'venue',
    'offers.priceCurrency' => '=EUR',
]]);

lynx()->run(UnmapTemplate::class, ['template' => 'event']);
```

Add or change nodes in a service provider:

```php
use LynxCms\Core\Pages\Page;
use LynxCms\Seo\JsonLd\JsonLd;
use LynxCms\Seo\JsonLd\JsonLdGraph;

app(JsonLd::class)->extend(function (Page $page, JsonLdGraph $graph): void {
    $graph->add(['@type' => 'SpecialAnnouncement', 'name' => 'Closed on Monday']);
});
```

`lynx()->seo($page)->jsonLd` holds the whole document.

## robots.txt

`/robots.txt` allows everything, then adds the lines from the settings and the lines modules add. Outside production it disallows everything. A real `public/robots.txt` file is served by the web server first, so delete it to use this one.

Modules add lines in a service provider:

```php
app(\LynxCms\Seo\RobotsTxt::class)->line(fn (): string => 'Sitemap: '.url('/sitemap.xml'));
```

## Admin

The **SEO** screen has three tabs:

- **Templates**: SEO fields on or off per template. Turning them on adds an SEO tab to the page editor, with Hide from search engines in its sidebar; turning them off removes the tab when nothing else is in it. Templates that had SEO before the tab existed move there on `migrate`, except fields an editor had already placed in another tab, column or section.
- **Structured data**: templates mapped to schema.org types.
- **Audit**: published pages with no description, a title another page also uses, or a title over 60 characters.
- **Settings**: opens the settings page.

Pages with SEO fields get an **SEO preview** link in the page editor. It shows the saved page as a search result and as a shared link. The screens need the `seo.manage` permission; the preview needs `pages.view`.

## Agents

The actions are MCP and agent tools: `seo_install`, `seo_enable_template`, `seo_disable_template`, `seo_map_template` and `seo_unmap_template`. The read tool `seo_inspect` gives a page's final tags in a language, so an agent can check its work after editing the `seo_*` fields.

## Configuration

Publish `config/lynx-seo.php` with `php artisan vendor:publish --tag=lynx-seo-config`; its keys are listed in [Configuration](/reference/configuration).
