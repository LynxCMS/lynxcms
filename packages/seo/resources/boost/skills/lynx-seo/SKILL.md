---
name: lynx-seo
description: Add SEO tags to Lynx layouts and manage SEO settings. Use when writing a layout's head, turning SEO fields on for a template, or checking a page's title, description, share image, canonical link or robots rule.
---

# Lynx SEO

`lynxcms/seo` writes the title, description, canonical, robots, Open Graph, X (Twitter) and hreflang tags, and serves `/robots.txt`.

## Rendering

```blade
<head>
    <meta charset="utf-8">
    <x-lynx-seo :page="$page" />
</head>
```

It replaces `<title>` and `@hreflang`. For a filtered list, override per response: `<x-lynx-seo :page="$page" robots="noindex, follow" :canonical="$page->url" />` (keys `title`, `description`, `canonical`, `robots`; the same array works for `@seo($page, [...])` and `lynx()->seo($page, [...])`). `@seo($page)` is the same; `lynx()->seo($page)` returns a `SeoData` object for a custom head.

## Fields

Turn SEO on per template; pages then get `seo_title`, `seo_description`, `seo_image`, `seo_noindex` and `seo_canonical`:

```php
lynx()->run(\LynxCms\Seo\Actions\EnableSeo::class, ['template' => 'article']);
```

Empty fields fall back to the page title in the site's title pattern, the first of `lynx-seo.description_fields` with text, the first image of `lynx-seo.image_fields`, then the defaults on the settings page (`lynx()->settings('seo-settings')`).

Outside production every page says noindex and robots.txt disallows everything (`lynx-seo.block_non_production`).

## Structured data

Each page gets a JSON-LD graph (WebSite, Organization, WebPage, BreadcrumbList). Map a template to a schema.org type on the SEO settings page (`seo_schemas` matrix, item type `mapping`: `seo_schema_template`, `seo_schema_type`, `seo_schema_map`), or on the admin's Structured data tab. Map lines read `property: source`, where a source is a field, `@title`, `@url`, `@published`, `@modified`, a constant such as `=EUR`, or `@organization` (the site's owner, for `author`, `publisher`, `provider` or `organizer`):

```
name: @title
startDate: starts
location: venue
offers.priceCurrency: =EUR
```

Add other nodes with `app(\LynxCms\Seo\JsonLd\JsonLd::class)->extend(fn ($page, $graph) => $graph->add([...]))`.

## Checking

The `seo_inspect` read tool gives a page's final tags in a language. Managing SEO needs the `seo.manage` permission.
