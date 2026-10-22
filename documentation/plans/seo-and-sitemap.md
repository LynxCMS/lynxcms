# SEO and sitemap modules

## Context
Lynx has no SEO or sitemap code. Today the `<head>` holds only `<title>` and `@hreflang($page)`. ADR-0008 already names "global SEO" as settings-page content, and the plan names an unbuilt `SeoResolver`. The user wants two modules:
- SEO: meta tags, social cards, JSON-LD and other extras.
- Sitemap: splits into several files when it reaches a page limit, with the limit set in config and in the admin.

Decisions taken with the user:
- Two packages. The sitemap works on its own. When the SEO module is installed, the sitemap respects noindex and canonical through an exclusion hook.
- SEO fields are opt-in per template. Pages without them fall back to defaults.
- JSON-LD covers the site-wide graph automatically. On top of that, each template can be given a schema type with property→field mapping, set in the admin.
- The sitemap is built on request, cached, and the cache is dropped on `ContentChanged`.

Rules that apply:
- Every write is an action.
- Settings live on singleton system pages, as menus do (ADR-0008).
- The core does not depend on either module.
- Packages use only `@api` code.
- TDD; Pint, Rector and PHPStan at max.
- Pest on SQLite, PostgreSQL (DB_PORT=5418) and `DB_PREFIX`; admin `npm run check`.
- One branch and PR per step.
- Next ADRs: 0055 (SEO), 0056 (structured data) and 0057 (sitemap).

Patterns to reuse:
- `packages/menus/src/MenuSchema.php` and `Actions/InstallMenus.php`: fields and templates, a hidden system root page, an idempotent install.
- `packages/menus/src/Admin/AdminIntegration.php`: `Navigation::add`, `SchemaOwners`, `TemplateChoices::exclude`, `AdminBranches`, `Admin::routes`. `Admin/Forms/SettingsForm.php` saves through `pages.save`.
- `packages/forms`: package config with `mergeConfigFrom`, `HookRegistry::after(SavePage::class)` for validation, `ReadToolRegistry`, `EditorLinks::add`.
- `packages/core/src/View/HreflangBuilder.php` for hreflang links and the absolute-URL helper; `Publication::isPublished()`; `PageStatus`; `Page::localUrl()`, `isActiveIn()`, `$updatedAt`, `$publishedAt`.
- `packages/core/src/Search/UpdateSearchIndex.php` shows how to listen to `ContentChanged`.
- `lynx()->settings($template)` reads singleton settings pages, cached per request.
- `packages/media`: `MediaList::first()?->size(1200, 630)->url()` for `og:image`.
- Wiring a new package: root `composer.json` (require, PSR-4, providers), `testbench.yaml`, `tests/TestCase.php` providers, `phpstan.neon.dist`.

## Step 1: SEO basics (`feat/seo-module`, ADR-0055)

### Package `packages/seo` (`LynxCms\Seo`)
- Package files:
  - `composer.json` requires `lynxcms/core` and suggests `lynxcms/admin` and `lynxcms/media`.
  - `SeoServiceProvider` (`@api`).
  - `config/lynx-seo.php` holds:
    - `description_fields`, the fallback list, e.g. `['summary', 'body']`;
    - `image_fields`;
    - `description_length` (160);
    - `noindex_hidden` (true);
    - `robots.block_non_production` (true).
- `SeoSchema`:
  - Page fields:
    - `seo_title` (text, translatable);
    - `seo_description` (textarea, translatable);
    - `seo_image` (images, `max_files: 1`; only when the media package is present);
    - `seo_noindex` (toggle);
    - `seo_canonical` (url).
  - Settings template `seo-settings` (singleton, no URL), with page `/_seo/`. Its fields:
    - `seo_site_name` and `seo_title_pattern` (translatable; default `{title} | {site}`);
    - `seo_default_description` and `seo_default_image`;
    - `seo_org_type` (Organization, LocalBusiness or Person), `seo_org_name`, `seo_org_logo`, `seo_org_same_as` (one URL per line);
    - `seo_twitter`;
    - `seo_robots_extra` (textarea);
    - `seo_schemas` (matrix, see Step 2).

### Actions
- `seo.install`: idempotent, the same shape as `InstallMenus`.
- `seo.enable_template {template}`: runs install, then `UpdateTemplate` adds the `seo_*` page fields.
- `seo.disable_template {template}`: removes those fields from the template. Values stay as orphans (rule 6) and the change is marked destructive=false.
- Each action gets tests for the happy path, validation, rollback and dry run.

### Rendering
- `SeoResolver` (`@api`): `resolve(Page): SeoData` returns a readonly DTO with:
  - `title`: `seo_title`, else the page title, then the pattern applied;
  - `description`: `seo_description`, else the first non-empty `description_fields` value with tags stripped and cut at a word, else the settings default;
  - `canonical`: `seo_canonical` or the absolute `localUrl()`;
  - `robots`: noindex when `seo_noindex` is set, the page is hidden (config), the site is not in production (config), or the page is a draft preview;
  - `image`: `seo_image`, then `image_fields`, then the default image, as an absolute URL with width and height;
  - `alternates`, from `HreflangBuilder::links()`;
  - `locale` and alternate locales;
  - `ogType` (`article` when mapped, else `website`);
  - `twitter`;
  - `jsonLd`, from Step 2.
- `<x-lynx-seo :page="$page" />` renders:
  - `<title>`, `meta description`, `canonical` and `robots`;
  - the `og:*` tags (title, description, url, image with width and height, locale, locale:alternate, site_name, type);
  - the `twitter:*` tags;
  - the hreflang links;
  - JSON-LD in `<script type="application/ld+json">`, encoded with `JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
  - The component replaces `<title>` plus `@hreflang` in layouts.
- `@seo($page)` is a directive alias.
- `lynx()->seo($page)` is a macro returning `SeoData`, for custom heads.
- `/robots.txt` route:
  - It is registered only when `public/robots.txt` does not exist, since a file there would be served first anyway.
  - Outside production it returns `User-agent: *` and `Disallow: /`.
  - In production it returns `Allow`, then the `seo_robots_extra` lines, then the lines registered by other modules through `RobotsTxt::line()` (`@api`). The sitemap uses this.

### Admin
- `AdminIntegration`:
  - an "SEO" nav item (permission `seo.manage`, `requires: pages.view`);
  - a settings screen editing `/_seo/` through `pages.save`, with a Google result preview and a social card preview of the home page;
  - a templates list with SEO on/off toggles that call `seo.enable_template` and `seo.disable_template`;
  - `SchemaOwners`, `TemplateChoices::exclude('seo-settings')` and `AdminBranches`.
- Editor: on pages whose template has SEO fields, show a Google result preview and a social card preview fed by `SeoResolver`. They use an `EditorLinks` or editor panel extension point; whether one exists is confirmed first, and a small `@api` hook is added to the admin if needed.
- An "Audit" tab lists published pages with a missing or duplicated title or description, or a description that is too long. It is read only and paginated.
- The MCP read tool `seo_inspect {page}` returns the resolved `SeoData`.

### Tests (`tests/Feature/Seo/`)
- The resolver's fallbacks in each case: fields, pattern, description from body, default image.
- Per language with fallback.
- noindex for the flag, hidden pages, non-production and drafts.
- Canonical override.
- Component HTML escaping (a title holding `</script>` and quotes).
- robots.txt with and without the file and in each environment.
- The actions.
- Admin screens and permission refusal.

## Step 2: JSON-LD graph and type mapping (`feat/seo-json-ld`)
- `JsonLdGraph` (`@api`) builds one `@graph` with:
  - `WebSite` (name, url, inLanguage);
  - `Organization` or `Person` (name, logo, sameAs);
  - `WebPage` (name, description, url, inLanguage, isPartOf, breadcrumb, dateModified, primaryImageOfPage);
  - `BreadcrumbList`, from the ancestors that have URLs;
  - the mapped entity, linked by `@id` (`{url}#article` and so on).
- `SchemaTypes` is a catalogue of allowed types and properties. For each property it gives a value kind (text, html→text, date, datetime, image, url, number, page, list):
  - Article, BlogPosting, NewsArticle: headline, description, image, datePublished, dateModified, author;
  - Event: name, startDate, endDate, location, description, image, offers.price, offers.priceCurrency;
  - Product: name, description, image, sku, brand, offers.price, offers.priceCurrency, offers.availability;
  - LocalBusiness: name, telephone, address.*, openingHours, geo.latitude, geo.longitude;
  - Course and Person, with their basic properties;
  - FAQPage: mainEntity from a repeater, with question and answer subfields.
  - Built-in sources: `@title`, `@url`, `@published`, `@modified`, `@author`.
- Mapping storage: the `seo_schemas` matrix on `/_seo/`, one block per template with subfields:
  - `seo_schema_template` (text);
  - `seo_schema_type` (choice from `SchemaTypes`);
  - `seo_schema_map` (textarea of `property: field` lines, with dotted paths for repeater subfields).
  - The admin shows it as a dedicated screen with selects: template, type, and for each property a field select filtered by compatible field types.
- Mapping values (for structured pages such as events and business locations):
  - `field`: the value of a page field, converted by the property's kind.
  - `@title`, `@url`, `@published`, `@modified`, `@author`: built-in sources.
  - `=EUR`: a constant, e.g. `offers.priceCurrency: =EUR` or `eventAttendanceMode: =OfflineEventAttendanceMode`.
  - Dotted properties build nested nodes: `location.name`, `location.address.streetAddress` and `geo.latitude` give `Place`, `PostalAddress` and `GeoCoordinates` with their `@type` taken from the catalogue.
  - Page reference field to a property whose kind is `page`, e.g. `location: venue`: when the referenced page's template is itself mapped (`venue` → `Place` or `LocalBusiness`), its node is embedded once in the graph and linked by `@id`. Otherwise the page's name and url are used. A depth limit of 2 prevents cycles.
  - Repeater or matrix to a list property, e.g. `openingHoursSpecification: hours` with `dayOfWeek: hours.day`, `opens: hours.opens` and `closes: hours.closes`, or `subEvent`, `offers` and FAQ `mainEntity`: each enabled item becomes one node.
  - Choice fields give their stored value, and an optional value map in the catalogue covers enums such as `eventStatus` and `availability`.
- Examples to ship in the docs and test fixtures:
  - An `event` template (date, end_date, venue reference, price, image) maps to `Event`. Its location is the mapped `venue` page, a `Place` with `PostalAddress` and `geo`. Offers come from price with a constant currency. Past events get no special handling: the dates say it.
  - A `location` template (one page per shop or office: address fields, phone, lat/lng, an hours repeater) maps to `LocalBusiness` or a subtype (`Restaurant`, `Store` and so on, chosen per template). Each location node gets `parentOrganization` pointing at the site-wide Organization `@id`, so several branches share one brand.
- `SeoSettingsValidator` is an `after` hook on `pages.save` and `pages.save_draft` for `seo-settings`. It refuses, on item paths:
  - unknown templates, types, properties or fields;
  - incompatible field types;
  - duplicate templates.
- `JsonLd::extend(fn (Page $page, JsonLdGraph $graph))` (`@api`) lets developers add to or replace nodes in PHP.
- Tests:
  - the graph for a plain page;
  - each catalogued type, with values converted (dates in ISO 8601, absolute image URLs, page references as URLs or names);
  - FAQ from a repeater;
  - fallback languages;
  - validator refusals;
  - the extend hook.

## Step 3: Sitemap (`feat/sitemap-module`, ADR-0057)

### Package `packages/sitemap` (`LynxCms\Sitemap`)
- `config/lynx-sitemap.php` holds:
  - `enabled`;
  - `per_file` (default 10000, capped at 50000 by the protocol);
  - `cache.store` and `cache.ttl`;
  - `include_hidden` (false);
  - `exclude_templates` (`[]`).
- `SitemapSchema` defines the settings template `sitemap-settings` (singleton, no URL), with page `/_sitemap/` and fields:
  - `sitemap_per_file` (integer, empty means the config value);
  - `sitemap_exclude_templates` (text, comma separated).
  - The effective limit is the admin value, else the config value, clamped to 1 to 50000.
- Which pages: templates with URLs; published per `Publication::isPublished()`; not system; hidden pages only if configured; excluded templates left out. Pages are read in id order with `chunkById`, and `SitemapFilters::exclude(fn (Page, Language): bool)` (`@api`) can veto any of them.
  - With SEO installed, it registers a filter that drops `seo_noindex` pages and pages whose canonical points elsewhere.
- Split: a unit is a page. Each page gives one `<url>` per language it is active and published in, with `xhtml:link` alternates for every language plus `x-default`, and `<lastmod>` from `updatedAt`.
  - Pages per file = `floor(limit / active languages)`, at least 1. That keeps each file under the URL limit and makes parts deterministic and cheap to page, by id offset.
- Grouping, `group_by` in config and in the admin (`sitemap_group_by` choice):
  - `none` (default): one sequence of parts over all pages.
  - `template`: one sitemap per template, each split by the same limit. Large sites can then see indexing per content type in Search Console.
  - Pages per part are computed per group, and empty templates are left out of the index.
- Routes (registered before the fallback page route):
  - `/sitemap.xml`:
    - with `none`, a `urlset` when everything fits in one file, else a `sitemapindex` listing `/sitemap-{n}.xml`;
    - with `template`, always a `sitemapindex` listing `/sitemap-{template}-{n}.xml` for every template that has pages;
    - each index entry carries its part's newest lastmod.
  - `/sitemap-{n}.xml` and `/sitemap-{template}-{n}.xml` return a part. They 404 when out of range, for an unknown or excluded template, or when the grouping mode doesn't match.
  - Template names are slugs, and a regex constraint on the route keeps `{template}-{n}` unambiguous: the trailing `-{digits}` is the part number.
  - Responses are `application/xml` with an ETag.
- Cache: each part and the index are cached under a version key. The `InvalidateSitemap` listener on `ContentChanged` bumps the version, plus on saves of `/_sitemap/`. Rendering streams XML with `XMLWriter`.
- Robots: if the SEO module is present, the sitemap adds `Sitemap: {absolute}/sitemap.xml` through `RobotsTxt::line()`.

### Actions and command
- `sitemap.install` (idempotent) and `sitemap.clear_cache`, each with the four action tests.
- `lynx:sitemap`: prints parts and counts; `--warm` fills the cache; `--clear`.

### Admin
- A "Sitemap" nav item (permission `sitemap.manage`).
- The screen has:
  - a limit per file input, showing the config default as placeholder and the 50000 maximum;
  - excluded templates as checkboxes;
  - a "Group by" radio: "One list" or "One sitemap per template";
  - stats: pages, URLs, parts, with links to each part;
  - a "Clear cache" button.
- It saves through `pages.save`.

### Tests (`tests/Feature/Sitemap/`)
- A single file under the limit, an index over it, and the exact split with a limit of 3 across 2 languages.
- Grouping by template: the index lists one entry per template, each split separately; empty and excluded templates are left out; a mismatched URL form returns 404; switching the mode in the admin drops the cache.
- Part 404s.
- Published, unpublished, hidden, system and URL-less templates; excluded templates.
- The admin limit overriding config, and the clamping.
- The noindex filter when SEO is present.
- Cache invalidation after `PageSaved`.
- Valid XML against the namespaces; hreflang alternates.
- Admin screen and permission.

## Step 4: Esfera Cúbica demo
- Install both modules and turn SEO on for `project`, `about` and `contact`.
- Fill in the site name, organization, logo and social links.
- Map `project` to `CreativeWork`; if that type is missing from the catalogue, use `Article` and add `CreativeWork`.
- Check `/`, `/projetos/terras-de-xisto/` and `/en/...` heads, plus `/sitemap.xml` with a small admin limit so the split shows.
- Back up the demo database first.

## Docs
- `docs/front-end/seo.md` and `docs/front-end/sitemap.md` (no dash punctuation).
- `docs/reference/configuration.md` gets the two config files; `artisan.md` gets `lynx:sitemap`; `admin/_index.md` gets the screens.
- ADR-0055, ADR-0056 and ADR-0057 in `documentation/ADRs.md`.
- The roadmap is updated.
- Boost skills `resources/boost/skills/lynx-seo` and `lynx-sitemap`.

## Verification
- The full local check script on every branch: composer check, Pest on SQLite, pgsql and prefix, admin dist.
- In the browser with agent-browser:
  - the SEO settings and previews, the template toggle, the editor preview, the audit;
  - the sitemap screen: change the limit and watch `/sitemap.xml` turn into an index.
- Validate the output:
  - JSON-LD with a schema validator, by pasting it in;
  - the sitemap XML with `xmllint --schema` against the sitemaps.org XSD.
- Check `curl /robots.txt` in local and production environments.

## To confirm while implementing
- Whether the admin page editor has a panel extension point for the previews, or needs a small `@api` addition.
- Whether `images` resolves to absolute URLs on the configured disk. If not, make them absolute with `url()`.
