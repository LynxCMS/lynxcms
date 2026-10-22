---
title: Configuration
description: The settings in config/lynx.php, config/lynx-admin.php and the modules' config files.
order: 3
---

# Configuration

Publish the files to change them:

```bash
php artisan vendor:publish --tag=lynx-config
php artisan vendor:publish --tag=lynx-admin-config
```

## config/lynx.php

| Key | Default | Meaning |
| --- | --- | --- |
| `routes.enabled` | `true` (`LYNX_ROUTES`) | Register the catch-all page route. |
| `routes.middleware` | `['web']` | Middleware of the page route. |
| `trailing_slash` | `false` (`LYNX_TRAILING_SLASH`) | End page URLs with a slash (`/courses/risk/`, as ProcessWire writes them), e.g. to keep a migrated site's addresses. By default they have none (`/courses/risk`), as Laravel writes them. Both forms open the page. |
| `views_path` | `templates` | Where template views live. |
| `localization.remember` | `true` | Remember the visitor's language in the session and on the user. |
| `localization.accept_language` | `false` | Use the browser's language on a first visit. |
| `localization.redirect_default_prefix` | `true` | Redirect URLs that carry the default language's prefix. |
| `backups.path` | `storage/app/lynx/backups` | Where `lynx:backup` writes. |
| `backups.keep` | `14` | Backups kept. |
| `backups.schedule` | `null` | `daily` or `hourly` to run backups from the scheduler. |
| `revisions.keep` | `50` | Revisions kept per page. |
| `edit_lock_seconds` | `120` | How long an editor's lock lasts without renewal. |
| `permissions.anonymous_role` | `null` | Role for MCP clients and agents acting for no user. |
| `agents.allowed_languages` | `[]` | Languages agents may write; empty means all. |
| `agents.default_limit`, `agents.max_limit` | `25`, `100` | Page limits for agent searches. |
| `mcp.web.enabled` | `false` | Serve the MCP server over HTTP at `mcp.web.path`. |
| `ai.provider`, `ai.model`, `ai.translation_model` | `null` | The agent's provider and models. |

## config/lynx-admin.php

| Key | Default | Meaning |
| --- | --- | --- |
| `prefix` | `lynx` | URL prefix of the admin. |
| `middleware` | `['web', 'auth']` | Middleware of every admin route. |
| `auth.enabled` | `auto` | Lynx's sign-in screens; see [Sign-in](/docs/admin/sign-in). |
| `auth.invitation_days` | `7` | How long an invitation link works. |
| `theme` | `system` | `light`, `dark` or `system`. |
| `appearance.*` | | BlatUI options: base, theme, radius, font, heading font, shadow, spacing, tracking, input style, font loading. |
| `stylesheets` | `[]` | Extra stylesheets loaded after the admin CSS. |
| `schema_editing` | `true` | Whether the schema screens can change the schema. |
| `tree.per_page` | `20` | Pages per page in the tree. |

## config/lynx-seo.php

Comes with `lynxcms/seo`; publish it with `php artisan vendor:publish --tag=lynx-seo-config`.

| Key | Default | Meaning |
| --- | --- | --- |
| `title_pattern` | `{title} \| {site}` | Titles of pages without an SEO title. Editors can change it per language in the SEO settings. |
| `description_fields` | `['summary', 'body']` | Fields read, in order, for a description when a page sets none. |
| `description_length` | `160` | Longer descriptions are cut at a word. |
| `image_fields` | `[]` | Image fields read, in order, for the share image when a page sets none. |
| `noindex_hidden` | `true` | Hidden pages ask robots not to index them. |
| `block_non_production` | `true` (`LYNX_SEO_BLOCK_NON_PRODUCTION`) | Outside production, every page says noindex and `robots.txt` disallows everything. |
| `audit_limit` | `2000` | Pages the admin's SEO audit checks at most. |
| `robots.enabled` | `true` | Serve `/robots.txt`. |

## config/lynx-sitemap.php

Comes with `lynxcms/sitemap`; publish it with `php artisan vendor:publish --tag=lynx-sitemap-config`.

| Key | Default | Meaning |
| --- | --- | --- |
| `enabled` | `true` (`LYNX_SITEMAP`) | Serve `/sitemap.xml` and its parts. |
| `per_file` | `10000` | URLs per file before the sitemap splits into parts under an index; 50000 at most. Editors can change it in the admin. |
| `group_by` | `none` | `none` for one list of parts, `template` for one sitemap per template. Editors can change it in the admin. |
| `include_hidden` | `false` | List hidden pages too. |
| `exclude_templates` | `[]` | Templates whose pages never appear; editors can add more in the admin. |
| `cache.store` | `null` | Cache store for the built files; `null` uses the default store. |
| `cache.ttl` | `86400` | Seconds a built file is kept. Any content change drops it sooner. |

## config/lynx-cache.php

Comes with `lynxcms/cache`; publish it with `php artisan vendor:publish --tag=lynx-cache-config`. See [Page cache](/docs/front-end/page-cache).

| Key | Default | Meaning |
| --- | --- | --- |
| `pages.enabled` | `false` (`LYNX_PAGE_CACHE`) | Store guest pages and send them again until content changes. |
| `pages.driver` | `files` (`LYNX_PAGE_CACHE_DRIVER`) | `files` for HTML files on this server, `store` for a cache store every server shares. |
| `pages.path` | `public/lynx-cache` | Folder of the `files` driver. |
| `pages.compiled` | `null` | Settings file `serve.php` reads; `null` uses `bootstrap/cache/lynx-cache.php`. |
| `pages.store` | `null` | Cache store of the `store` driver and of the clear counters; `null` uses the default store. |
| `pages.hosts` | `[]` | Hosts whose pages are stored; empty uses the host of `app.url`. |
| `pages.lifetime` | `0` | Seconds a page is kept when its template rule sets none; 0 keeps it until content changes. |
| `pages.bypass_cookies` | `lynx_bypass`, `lynx_page_password` | Requests with one of these cookies always go to Laravel. |
| `pages.ignored_parameters` | `utm_*`, `gclid`, `fbclid`, ... | Query parameters dropped before looking a page up. |
| `pages.templates` | `['*' => []]` | Template rules: lifetime, allowed query parameters and what a change clears. |
| `pages.csrf` | `refresh` (`LYNX_PAGE_CACHE_CSRF`) | `refresh` stores pages holding the CSRF token with a placeholder that a script replaces with a fresh token; `skip` leaves them out. A template rule's `csrf` overrides it. |
| `pages.max_variants` | `50` | Query variants stored per page. |
| `pages.edge_ttl` | `0` | Seconds an edge cache may keep a sent page; 0 sends no public `Cache-Control`. |
| `pages.debug` | `false` (`LYNX_PAGE_CACHE_DEBUG`) | Add `X-Lynx-Cache` headers with the reason a page was not stored. |
| `fragments.enabled` | `true` (`LYNX_FRAGMENT_CACHE`) | Keep what `@lynxCache` and `lynx()->cache()->remember()` wrap. |
| `fragments.store` | `null` | Cache store for fragments; `null` uses the default store. |
| `fragments.ttl` | `null` | Seconds a fragment is kept when it sets none; `null` keeps it until what it depends on changes. |
| `fragments.vary_cookies` | `lynx_page_password` | Guests carrying these cookies get their own copies. |

## config/lynx-static.php

Comes with `lynxcms/static-site`; publish it with `php artisan vendor:publish --tag=lynx-static-config`. The keys are listed in [Static site export](/docs/front-end/static-site#configuration).
