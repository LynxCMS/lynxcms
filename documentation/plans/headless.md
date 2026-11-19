# Plan: headless Lynx, with an Astro front end

Status: proposed on 2026-11-19, nothing built. Open questions are listed at the end. This plan details the "Headless REST API" and "Webhooks" items of the implementation plan's v2 section.

## Why

Some teams want their front end in Astro (or Next, Nuxt, SvelteKit) and Lynx only as the place where editors work. Today Lynx renders pages itself with Blade, and its content leaves only through PHP and MCP: `compared.md` says "no REST or GraphQL API yet". An Astro site needs content over HTTP, in a shape that stays stable across releases, plus previews, rebuilds and forms that work without Livewire.

Two ways to run Astro, and the plan serves both:

* **Static (SSG):** Astro fetches everything at build time. Lynx calls a deploy hook when content changes. Cheapest to host; drafts can't be previewed.
* **Server rendered (SSR or hybrid):** Astro fetches per request and caches. Needed for draft previews, gated pages and search.

## What exists and is reused

| Piece | Where | Use |
|---|---|---|
| Pages API and selectors that leave out pages the caller may not see | `Api/PagesApi`, `Query/PageScope` | Selectors become the list endpoint's query |
| `LYNX_ROUTES=false` | `config/lynx.php` | Turns off the Blade page route; the admin keeps working |
| Admin decoupled from rendering | ADR-0019 | Editors keep the admin unchanged |
| Sanctum tokens with a required ability | `mcp/Http/RequireTokenAbility` | Same pattern for `lynx:read` |
| `lynx.permissions.anonymous_role` | config | Role for callers acting for no user |
| `ContentChanged` with `affectedPages()` | core events | Trigger for rebuilds and cache purges |
| Drafts and `?preview=draft` | ADR-0039 | Session based, on Lynx's own route only |
| `Pagefile::temporaryUrl()` | media | Private files on the Astro site |
| Old URLs (redirects after a rename) | publishing | Exported so Astro answers old addresses too |

## Gaps

1. **No HTTP read API.** Content leaves only through PHP and MCP.
2. **No stable JSON per field type.** `Page::toArray()` is `@internal`: page references come out as bare ids, media as objects, matrices without their item types.
3. **Image sizes are made only by Blade.** `Pageimage::file()` writes a variation the first time a template asks for it; nothing over HTTP does.
4. **Page gates and page passwords run on the Blade route.** `PageGates` takes the request on `PageController`; an API that skips them leaks gated content. This is the main security risk.
5. **URLs assume Lynx serves the site.** View links in the admin, canonical URLs, hreflang, the sitemap and links in emails use `APP_URL`.
6. **Previews need a Lynx session.** An Astro site has no Lynx session.
7. **Nothing calls out when content changes.** No webhooks.
8. **Forms are Livewire components.** Astro can't render or submit them.
9. **Doctor and modules assume Blade.** The template views check would warn about every template; the page cache, static site export and editable texts in views do not apply.
10. **Rich text links are stored as paths** (the link dialog inserts the page's path). Formatters such as heading anchors run at render. The API must return formatted HTML, and Astro must know the redirects, or renamed pages break links.

## Phase 1: static Astro site, rebuilt on publish

### 1. Read API (`lynxcms/api` package, or a module of core)

Routes under `/api/lynx/v1`, JSON only, `auth:sanctum` plus the `lynx:read` ability, or no token when the site allows it (then `anonymous_role` decides). Every request runs as its actor, so `PageScope` decides what is visible, as in MCP.

| Route | Returns |
|---|---|
| `GET pages?selector=&lang=&page=&per_page=&expand=` | A page of results, with `meta.total` and links |
| `GET pages/by-path?path=&lang=` | One page by its URL, the route Astro resolves through; 301 data when the path is an old URL |
| `GET pages/{id}?lang=&expand=` | One page |
| `GET languages` | Codes, names, prefixes, the default |
| `GET strings?lang=` | Site wide and template wide texts |
| `GET redirects` | Old URLs and where they go |
| `GET paths?lang=` | Every visible URL with its page id, template and `updated_at`, for `getStaticPaths()` and the sitemap |

Responses carry an `ETag` from the page's revision and answer `304` to `If-None-Match`. CORS origins come from `lynx-api.cors.origins`. Rate limited per token.

### 2. Field JSON format (the decision hardest to change later)

A new `FieldType::toApi(mixed $value, array $settings, ApiContext $context): mixed`, required for every type, core and module. It is public API from its first release. Proposed shapes:

| Type | JSON |
|---|---|
| text, email, url | string |
| textarea plain | string |
| textarea html, markdown | `{ "html": "..." }` after formatters, Markdown converted |
| integer, decimal | number |
| toggle | boolean |
| date, time | ISO 8601 string |
| choice | `{ "value": "x", "label": "X" }`, a list when several |
| page reference | `{ "id", "url", "name", "template" }`; the full page with `expand=field` (one level) |
| page table | list of the same |
| repeater | list of `{ "id", "fields": {...} }` |
| matrix | list of `{ "id", "type", "fields": {...} }` |
| component | `{ "fields": {...} }` |
| images | list of `{ "url", "width", "height", "alt", "focus", "mime", "variants": { preset: { url, width, height } } }` |
| files | list of `{ "url", "name", "bytes", "mime", "description", "tags" }`; private files get a temporary URL |

A page: `{ id, template, parent_id, name, url, language, languages: { code: url }, status, revision, updated_at, fields: {...}, seo: {...} }`. Translatable fields come out in the asked language, with Lynx's fallback; `meta.fallback` lists the fields that fell back.

### 3. Images

The API returns the variants of the presets in `lynx-media.presets` (made when the response is built, then kept). Astro picks one, or sends the original through its own `<Image>` service. An on-demand route with arbitrary sizes is left out: without a signature anyone could make the server write unlimited files.

### 4. Gates and passwords

The API runs `PageGates` for each page it returns, with the API request. A page a gate refuses is left out of lists and answers 403 (or 401 with the gate's reason) on `by-path`. Page password pages are left out unless the token's role may bypass them. Tests prove that gated content never appears, whether through a list, a page reference with `expand`, or `paths`.

### 5. Public URL and a headless mode

* `lynx.frontend.url` (`LYNX_FRONTEND_URL`): the site's public address. Page URLs in the API, admin View links, canonical and hreflang data, sitemap entries and links in notifications use it. Defaults to `APP_URL`, so nothing changes for Blade sites.
* `lynx.headless` (`LYNX_HEADLESS`, implies `LYNX_ROUTES=false`): the template views doctor check is skipped, and the admin hides features that need Blade (page cache, static export, editable texts in views).

### 6. Webhooks (`lynxcms/webhooks` module)

* Endpoints in Setup › Webhooks: a URL, the events (any change, publish, trash, delete), an optional secret.
* A listener on `ContentChanged` queues one job per endpoint, debounced (`lynx-webhooks.debounce_seconds`, 30), so a burst of saves is one deploy.
* Body: `{ event, pages: affectedPages(), at }`, signed with HMAC SHA-256 in `X-Lynx-Signature`.
* Retries with backoff, a delivery log with status and response time, a Send test button.
* Ready to use with Netlify, Vercel and Cloudflare Pages deploy hooks, which ignore the body.

### 7. `@lynxcms/astro` (npm package, its own repository)

* An Astro content layer loader: `lynxLoader({ url, token, selector, lang })` fetches through the API at build, with ETags for incremental builds.
* Helpers: `getPage(path, lang)`, `getPaths()`, `<LynxImage>` (picks a preset or the original), redirects into `astro.config` at build.
* A starter Astro site in the guides, like the bilingual website guide.

### Tests to write first (phase 1)

* A token without `lynx:read` gets 403; a token with it reads only what its role may see.
* Unpublished, hidden, gated and password protected pages never appear in `pages`, `paths`, `by-path` or through `expand`.
* `by-path` resolves every language prefix and answers an old URL with its target.
* Each core field type's `toApi()` output matches a stored JSON snapshot, so a change is a visible diff.
* Rich text links and formatters come out as Blade renders them.
* Image presets in the response exist on disk after the request.
* A page save fires one debounced webhook per endpoint with a valid signature; a failing endpoint is retried and logged.
* `LYNX_FRONTEND_URL` changes URLs in the API, the admin's View link, the sitemap and notifications.

## Phase 2: server rendered Astro

* **Previews:** the admin's Preview opens `LYNX_FRONTEND_URL/_preview?token=…&path=…`. The token is signed, short lived (15 minutes), and bound to one page and the editor. The API accepts it in `X-Lynx-Preview` and returns the draft. Astro renders that route on the server, never cached.
* **Forms:** `GET forms/{name}?lang=` returns fields, labels, options and rules; `POST forms/{name}` (JSON or multipart) runs `SubmitForm`. Without CSRF, spam protection is a honeypot, the time check, a rate limit per IP, and optional Turnstile. Forms that hand out files return signed links, as the `afterSent` hook of custom form components does on Blade sites.
* **Menus:** `GET menus/{name}?lang=` with resolved URLs and the current item left to Astro.
* **SEO:** `seo` in each page response (title, description, Open Graph, JSON-LD from `SeoResolver`), and `GET robots`.
* **Search:** `GET search?q=&lang=` over the search index.
* **Cache purge:** for SSR hosts, the webhook body's `pages` lets the front end purge by cache tag.

## Phase 3: developer experience

* `php artisan lynx:types --ts`: TypeScript and Zod types from the templates, as `FieldProperties` does for PHPStan, used by the Astro loader's schema.
* A live preview frame beside the page editor, reloading on save.

## Out of scope for now

* **Writes over REST.** Editors use the admin; agents use MCP. When writes come, they go through actions, as the implementation plan says.
* **GraphQL.** Selectors plus `expand` cover the queries a front end makes.

## Open questions

1. **Static only first, or static and server rendered together?** Draft previews depend on server rendering.
2. **Images:** presets made by Lynx (proposed), or originals only and Astro resizes?
3. **Package layout:** a separate `lynxcms/api` package (proposed: sites that don't need it don't get the routes), or part of core?
4. **Anonymous reads:** allow the API without a token on public sites (proposed: off by default), so a browser can call it directly?
5. **`expand` depth:** one level (proposed), or a configurable limit?

## ADR to write when phase 1 starts

The field JSON format and the `toApi()` contract, the token model (`lynx:read`, roles, anonymous), and gates applied to the API. Both are public API from the first release.
