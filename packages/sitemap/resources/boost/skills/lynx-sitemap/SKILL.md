---
name: lynx-sitemap
description: Configure and check the Lynx XML sitemap. Use when submitting a sitemap, changing how many URLs a file holds, grouping it by template, or leaving pages out.
---

# Lynx sitemap

`lynxcms/sitemap` serves `/sitemap.xml`: every published page with a URL, one `<url>` per language with hreflang alternates. Past the limit of URLs per file it becomes an index of parts (`/sitemap-1.xml`), or, grouped by template, of `/sitemap-{template}-{n}.xml`.

## Settings

Config `lynx-sitemap`: `per_file` (10000, at most 50000), `group_by` (`none` or `template`), `include_hidden`, `exclude_templates`. Editors override the first two and add excluded templates on the admin's Sitemap screen (settings page `lynx()->settings('sitemap-settings')`).

## Leaving pages out

```php
app(\LynxCms\Sitemap\SitemapFilters::class)->exclude(fn ($page, $language): bool => $page->template()->name === 'thank-you');
```

With `lynxcms/seo`, pages marked noindex or with a canonical URL elsewhere are left out already.

## Cache

Files are cached and rebuilt after any content change. `php artisan lynx:sitemap --warm` builds them all; `--clear` drops them. Managing the sitemap needs `sitemap.manage`.
