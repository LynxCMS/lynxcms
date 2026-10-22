# Plan: page cache, fragment cache and islands

## Goal

A demanding site needs ProcessWire ProCache speed: guest pages served as stored HTML, ideally by nginx or Apache with PHP never running, and cleared on their own when content changes. Developers also need ProcessWire's markup cache, to cache parts of a page that is not cached as a whole. Asset minifying, merging and CDN rewriting are out of scope.

## Decisions taken

| Question | Decision |
| --- | --- |
| Build on spatie/laravel-responsecache? | No. Every cached hit there boots Laravel and starts a session, and the Lynx half (what to clear, when a page is safe) is written anyway. We borrow ideas from its source, listed below. |
| Packaging | A module: `lynxcms/cache`, namespace `LynxCms\Cache`, actions `page_cache.*`, config `lynx-cache`. It holds the page cache, the fragment cache and islands. |
| Where pages are stored | A `PageStore` with two drivers. `files`: HTML files on local disk, served by nginx, Apache or Caddy, or by a pre-boot hook in `public/index.php`. `store`: any Laravel cache store (Redis, Laravel Cloud KV), shared by every replica, served by an early PHP middleware. One server: `files`. Laravel Cloud or several servers: `store`. |
| What clears on a change | Any `ContentChanged` clears every page by default, since menus and listings can show any page. A template rule can narrow it, ProCache style: the page, its parents, children, siblings, the home page, chosen pages. |
| Lifetime | Per template rule, with a default. Empty means the default, 0 means until cleared. |
| Query strings | Off unless a template rule allows named parameters (`page`, `sort`). Tracking parameters (`utm_*`, `gclid`, `fbclid`, configurable) are dropped first. Any other parameter means no cache. Variants per page are capped. Web servers serve only the page without a query; variants go through PHP. |
| Pages with forms or Livewire | Stored by default with token refresh: a placeholder in the stored page and a script that fetches a fresh token after load. Cached forms lose the honeypot's timing check, so forms that need it go in islands; `csrf: skip` leaves such pages out. Decided after a spike (Part 7). |
| Edge caches | Optional `edge_ttl`: stored guest pages get `Cache-Control: public, s-maxage` and no cookies, so the Laravel Cloud (Cloudflare) edge keeps them for that many seconds. Laravel Cloud has no per URL purge, so this is a short micro cache. Off by default. |
| Content types | HTML only. |

## What may be stored

One `Eligibility` class decides, in two steps.

**The request**, checked before rendering and again by every server tier through a framework free `RequestRules` class: GET or HEAD; a query that is empty, or allowed, after dropping tracking parameters; a configured host (default: the host of `app.url`); no bypass cookie (`lynx_bypass`, `lynx_page_password`, configured names); a page route.

**The final response**, checked in the middleware's `terminate()`, after `RequestHandled` listeners ran (page passwords add their private headers there): status 200; `text/html`; no `private` or `no-store` in `Cache-Control` (this covers page passwords and draft previews without knowing them); no cookie other than the session and XSRF cookies; no current CSRF token in the body; no signed in user; the request path is the page's canonical path; the template has caching on; the page did not opt out (`@nocache`, `lynx()->cache()->skip()`).

`lynx_bypass` is set when a user signs in or opens a draft preview and removed on sign out, because every visitor carries a session cookie and that cannot be the signal. With debugging on, `X-Lynx-Cache: SKIP; reason=...` says why a page was not stored.

## Ideas taken from spatie/laravel-responsecache

Taken: decide while handling, write in `terminate()` and check the final response again; a cache error is a miss that gets reported, never a failed page; the CSRF placeholder replacer; stale while revalidate through `Cache::flexible` for the `store` driver; `X-Lynx-Cache` and `Age` headers, the key only with `app.debug`; events for hits, misses and clears.

Done differently: query parameters are sorted and allow listed (Spatie keeps their order, so the same page is stored twice); only a short list of headers is stored and never `Set-Cookie`; clearing bumps a generation counter instead of flushing the whole cache store; settings are read once and typed, not unserialized from middleware arguments; storing does not depend on `runningInConsole()`, so a warm command can render pages.

## Part 1: the package and the stores

`packages/cache`, wired like `packages/sitemap`: root `composer.json` (replace, PSR-4, provider), `testbench.yaml`, `tests/TestCase.php`.

- `config/lynx-cache.php`: pages (enabled, driver, path, hosts, lifetime, bypass cookies, ignored parameters, max variants, edge TTL, debug) and fragments (store, TTL).
- Table `lynx_page_cache` (key, host, path, query, page, parent, ancestors, language, template, created and expiry times) and the model `PageCacheEntry`: the index used to clear by page or template, prune and count.
- `FileStore`: writes to a temporary file and renames it, so readers never see half a page. File paths come from the page's canonical path, never from the request, lower case: `{host}/{path}/index.html`, variants `{host}/{path}/q-{hash}.html`. A full clear renames the host folder and deletes it.
- `CacheStore`: keys `lynx-cache:page:{generation}:{host}:{path}:{query}`. A full clear bumps the generation, which every replica sees at once.
- `Generation`: the generation changes when every page is dropped (the `store` driver keys on it); the revision changes on any clear, is read when rendering starts and compared before writing, so a page rendered while a clear ran is not stored.

## Part 2: storing and serving

- `StorePageCache`, a page route middleware, writes in `terminate()`. An optional HTML comment marks stored pages.
- `ServePageCache`, prepended to the HTTP kernel so it runs before the session starts, answers from either driver with `X-Lynx-Cache: HIT`. The expiry lives in the entry itself, so a hit needs no database.
- `serve.php`, required at the top of `public/index.php`, serves `files` entries before Laravel boots, from a compiled `bootstrap/cache/lynx-cache.php`. It is documented, not patched in.
- `lynx:page-cache:server nginx|apache|caddy` prints the rewrite rules; the admin shows the same text.

## Part 3: clearing

- Listen to `ContentChanged`. `affectedPages()` returning null clears everything, and so does any change while no template has a narrower rule, without loading a page. Otherwise each page's template rule decides, read from the index (template, parent, ancestors) so pages are loaded only when the changed page was never stored.
- A `HookRegistry` after hook on every action marks changes that fire no `ContentChanged` (fields, templates, page strings) and clears everything after commit, so a rolled back action clears nothing.
- Blade changes on deploy fire nothing: deploy scripts run `php artisan lynx:page-cache --clear`. Laravel Cloud's ephemeral disk empties `files` on its own.

## Part 4: actions, settings and commands

- `page_cache.install` creates the hidden settings page (ADR-0008). `page_cache.clear` takes `all`, page ids, a template or `fragments`, and lists what it would clear on a dry run. Permission `page_cache.manage`.
- Settings: on or off, default lifetime, bypass cookies, debug, and a repeater of template rules (template, lifetime, grace, clear scopes, chosen pages, allowed parameters, forms handling).
- `lynx:page-cache --clear --prune --stats --compile`; prune runs every minute from the scheduler.

## Part 5: admin

Settings > Page cache: counts and size, clear all, template rules, the server rules. The page editor gets "Clear this page's cache".

## Part 6: fragment cache

`lynx()->cache()->remember($name, fn () => ..., ttl: 3600, dependsOn: ['template:course', 'page:12'], vary: [...])` and `@lynxCache('nav', [...]) ... @endLynxCache` (not `@cache`, which other packages register). Built:

- An index table, `lynx_fragment_cache` (name, language, audience, vary, depends_on, size, times), written on a miss only, so a hit is one cache read. It lists fragments in the admin, and clearing works from it: a content change deletes the rows whose `depends_on` matches (`page:{id}`, `template:{name}`, or `any` for fragments without dependencies) and forgets their keys. This replaced the planned generation counters, which could not list what is stored.
- Copies per language, per `vary` value and per audience (guests, split by `vary_cookies` such as the page password cookie). Signed in users always get a fresh render: drafts and permissions make their output personal, and caching pays off for guests.
- The revision guard of the page cache applies; a fragment holding the CSRF token is not stored.
- Template dependencies need the changed pages' templates, so pages are loaded only when some fragment depends on a template.
- Admin: the page cache screen lists fragments, shows one's HTML as source and as a sandboxed preview, and clears one copy, a name, or all. `page_cache.clear` takes `fragments: true` or `fragment: name`.

## Part 7: forms and islands

1. Spike: a cached contact form page served (a) with its token replaced after the session starts and (b) through an island. Record how the installed Livewire reads the token, whether its snapshot checksum depends on the session, whether it starts components added later, and how the honeypot timestamp behaves.
2. Token replacement: a template rule option. The entry stores a placeholder and is served after `StartSession` with the live token. Web servers and `serve.php` never match these entries.
3. Islands: `<x-lynx-island view="partials.cart" />` and `<x-lynx-island livewire="lynx-form" :with="['form' => 'contact']" />` print a placeholder with an encrypted payload when the page is being stored, and render inline otherwise. `GET /lynx-island` renders every island of a page in one request and returns their HTML and a fresh token; a small script swaps them in before Livewire starts them.

### Spike results (Livewire 4.4.7, spatie/laravel-honeypot 4.7.3)

- Livewire reads the token when it sends each request: `meta[name=csrf-token]`, then `[data-csrf]`, then `livewireScriptConfig.csrf`. A token set after load is used.
- Snapshot checksums are an HMAC of the snapshot with the app key, not tied to the session: a stored component is valid for any visitor.
- Livewire starts components through Alpine's `interceptInit`, which Alpine's MutationObserver runs for added nodes, so islands need no manual start.
- Every component boot makes Livewire send `no-cache, must-revalidate, no-store, max-age=0, private` with `Expires: 1990` and `Pragma: no-cache`, for the back button. Eligibility ignores exactly that set.
- The honeypot only refuses a timestamp in the future, so a stored form always passes its timing check.

- Laravel 13's `PreventRequestForgery` accepts same-origin requests (`Sec-Fetch-Site: same-origin`) without checking the token, so on current browsers a stale token on a stored page does not break forms. The token matters for older browsers, other clients and apps without origin checks; token refresh covers those.
- Livewire injects its assets in a `RequestHandled` listener. Storing from the final response in `terminate()` keeps them, so Lynx needs nothing like statamic-livewire's `AssetsReplacer`. Islands must still make sure a page whose only component is inside an island gets Livewire's assets (and the component's `@assets` and `@script`), since Livewire renders no component on the stored page.
- Checked on a real nginx server (Ubuntu VM, plain HTTP): a visitor arriving on a stored page has no session, so a favicon 404 at load time started a competing session and the Livewire form got 419. The refresh script now sends the `XSRF-TOKEN` cookie (`X-XSRF-TOKEN`) instead of copied tokens (header or `_token` in the body) for `fetch` and `XMLHttpRequest`, and plain forms fetch a fresh token when the session changed. `Sec-Fetch-Site` comes only from secure contexts, which is why `localhost` hid this.
- Real servers (Ubuntu 26.04 VM, PHP-FPM 8.5): the printed rules for nginx 1.28, Apache 2.4 and Caddy 2.6 each send stored pages with PHP-FPM stopped, let query strings, bypass cookies and signed in users through to Laravel, and stop sending a page once a save deletes its file. `serve.php` answers stored pages in 3 to 5 ms before Laravel boots (55 ms to render). Laravel's stock Apache rule that drops trailing slashes redirects every Lynx URL, so the printed Apache rules say to remove it.
- Load test on the same VM (`ab`, keep-alive, 1 and 10 clients): 26 to 38 ms to render, 10.7 ms for a hit through the middleware (framework boot is most of it), 11.6 ms with the `store` driver, 0.35 ms through `serve.php`, 0.05 ms from nginx; a fragment around the course list saved 9 ms. Numbers in the "How fast" section of `docs/front-end/page-cache.md`. Found with it: web server rules sent stored files with `Last-Modified` and no `Cache-Control`, so browsers could reuse a cleared page; the rules now send PHP's `no-cache, private` (or the edge header) on stored pages only. `serve.php` loads its own classes, so it no longer fails with a 500 on every request when placed before the Composer autoloader.
- Same load test with PostgreSQL 18 on the VM: stored pages cost the same as on SQLite (no table read), except the `store` driver on a database cache store (21 ms, a connection per hit). Renders are 2 to 2.5 times slower (76 to 78 ms): a connection per request (7.75 ms, TLS and SCRAM, plus a cold server process) and about 80 queries per fresh process, 26 of them for the two menus (one table per field). Persistent connections (54 ms) and menus in fragments varied by page (39 ms) together bring it to 29 to 41 ms. Documented under "Rendering on PostgreSQL"; the fragment example for menus now varies by page, since menus mark the current link.
- Statamic answers token and `{{ nocache }}` regions in one `/!/nocache` request; Lynx does the same with `GET /lynx-cache/dynamic` (a GET needs no CSRF exemption), which returns islands too once they exist.

Token refresh shipped first, then became the default (`csrf: refresh`), since Laravel 13's origin check makes a stale token harmless on current browsers, checked end to end on the workbench: a guest loaded a stored course page, the script fetched a token and the Livewire enrol form saved. Islands follow for forms that need the timing check and for content that differs per visitor.

## Part 8: warming

`lynx:page-cache --warm` renders every published page with a URL whose template has a cache rule, in every language it is live in, through the HTTP kernel in the same process, and reports what was stored and why the rest was not (the middleware leaves its skip reason on the request). The page list comes from selectors in the module, so no `@api` URL lister was needed. Warming uses the `APP_URL` host; with the `files` driver each server warms its own disk.

## Order

Parts 1 to 3 in the first pull request, without `serve.php` and the server rules command, which come with the commands of Part 4. Then 4 and 5, then 6, then 7 after its spike, then 8. ADR-0064 records the decisions.

## Tests

Pest under `tests/Feature/Cache`, on SQLite and PostgreSQL and with `DB_PREFIX`. One test per eligibility rule, stored and skipped. Both drivers run the same contract tests. Clearing per event and per scope. The generation race. Unsafe paths and unknown hosts. Query variants: allowed, ignored, unknown, capped. Each action: happy path, validation failure, rollback, dry run. Fragments per language, audience and dependency. Islands refuse a tampered payload, and a form submits from a cached page. Before release, a Laravel Cloud staging run with two replicas checks that a save reaches both.
