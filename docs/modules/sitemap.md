---
title: Sitemap
description: An XML sitemap of every published page in every language, split into parts when it grows.
order: 5
---

# Sitemap

The sitemap comes with `lynxcms/sitemap`. Install the package and `/sitemap.xml` lists every published page whose template has URLs, in every language the page has a URL in. Submit that address to search engines.

## What it lists

- One `<url>` per page and language, with the page's other languages as `xhtml:link` alternates and an `x-default`.
- `<lastmod>` from the page's last change.
- Published pages only. Hidden pages are left out unless `include_hidden` is on.
- Templates left out in the config or in the admin never appear.
- With `lynxcms/seo`, pages marked noindex, and pages whose canonical URL is another page, are left out. `robots.txt` also names the sitemap.

## Parts

A sitemap file holds at most 50000 URLs. Past the limit set in the admin (or `per_file` in the config, 10000 by default), `/sitemap.xml` becomes an index of parts: `/sitemap-1.xml`, `/sitemap-2.xml` and so on.

Each page counts once for every active language, so with two languages and a limit of 10000 each part holds 5000 pages. Parts follow the order pages were created in.

### One sitemap per template

Big sites can group by template instead. `/sitemap.xml` is then always an index, with one sitemap per template that has pages, each split on its own: `/sitemap-event-1.xml`, `/sitemap-event-2.xml`, `/sitemap-product-1.xml`. Search consoles then show indexing per kind of page.

Choose it in the admin or with `'group_by' => 'template'` in the config.

## Cache

Each file is built on its first request and cached. Any change to pages, templates or languages drops the cache, so the next request builds it again. Responses carry an ETag, so a crawler that already has the current file gets a `304`.

```bash
php artisan lynx:sitemap            # the parts and their URLs
php artisan lynx:sitemap --warm     # build and cache every file now
php artisan lynx:sitemap --clear    # drop the cache
```

## Admin

The **Sitemap** screen sets the URLs per file, the grouping and the templates left out, lists the parts, and clears the cache. It needs the `sitemap.manage` permission. The settings are saved on a hidden system page, through `pages.save` like any page.

## In code

Leave pages out in a service provider:

```php
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Sitemap\SitemapFilters;

app(SitemapFilters::class)->exclude(fn (Page $page, Language $language): bool => $page->template()->name === 'thank-you');
```

`lynx()->sitemap()` gives the files: `root()`, `part($group, $n)`, `parts()` and `pageCount()`.

Agents and MCP clients get `sitemap_install` and `sitemap_clear_cache`.

## Configuration

Publish `config/lynx-sitemap.php` with `php artisan vendor:publish --tag=lynx-sitemap-config`; its keys are listed in [Configuration](/reference/configuration).
