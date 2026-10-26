---
title: Page cache
description: Guest pages stored as HTML and sent again without rendering, cleared when content changes.
order: 9
---

# Page cache

The page cache comes with `lynxcms/cache`. It stores the HTML of pages guests see and sends it again to the next guest, without loading the page or rendering Blade. With web server rules it is sent without PHP running at all, as ProcessWire's ProCache does. Saving content clears what it changed.

Install it, back up the database and apply its migrations:

```bash
composer require lynxcms/cache
php artisan lynx:migrate
```

Then turn it on in `.env`:

```dotenv
LYNX_PAGE_CACHE=true
```

The page editor has a **Cache** tab on every page with a URL. It says whether guests get a stored copy of the page and at which URLs, or why not (the cache is off, the template is not stored, or the page is unpublished). People who may manage the page cache (`page_cache.manage`) also see the template's rule, how to fix what keeps the page out, a button to clear the page, and a short guide to how the page cache works.

## Where pages are kept

| Driver | Where | Served by | For |
| --- | --- | --- | --- |
| `files` (default) | HTML files in `public/lynx-cache` | nginx, Apache or Caddy, or PHP before the session starts | One server |
| `store` | A Laravel cache store, such as Redis | PHP before the session starts | Laravel Cloud and several servers |

```dotenv
LYNX_PAGE_CACHE_DRIVER=store
```

On Laravel Cloud, use `store` with a Cloud cache (KV) attached. Each replica has its own disk, so files written on one replica could not be cleared from another. Without Octane each hit still boots the framework (about 10 ms, see [How fast](#how-fast)); with Octane the framework stays booted, so a hit costs little more than one cache read.

### Sending pages without PHP

Without anything else, PHP sends stored pages before Laravel starts the session, which already skips the page lookup and rendering. Two ways go further with the `files` driver.

**Web server rules.** Print them for your server and add them to its configuration:

```bash
php artisan lynx:page-cache:server nginx   # or apache, caddy
```

For nginx they look like this:

```nginx
set $lynx_cache "/lynx-cache/$host$uri/index.html";
set $lynx_cache_control "no-cache, private";
if ($request_method !~ ^(GET|HEAD)$) { set $lynx_cache /.lynx-no-cache; }
if ($args != "") { set $lynx_cache /.lynx-no-cache; }
if ($http_cookie ~* "(lynx_bypass|lynx_page_password)") { set $lynx_cache /.lynx-no-cache; }
if (!-f $document_root$lynx_cache) { set $lynx_cache_control ""; }

location / {
    add_header Cache-Control $lynx_cache_control;
    try_files $lynx_cache $uri $uri/ /index.php?$query_string;
}
```

Requests with a query string always go to PHP, which drops tracking parameters and sends allowed variants.

The rules send stored pages with the same `Cache-Control` header as PHP: `no-cache, private`, or `public, max-age=0, s-maxage=…` with an [edge TTL](#edge-caches). Without it, a web server sends the file with only its `Last-Modified` date, and browsers may show a cleared page again for a while without asking. Static files keep their own headers. On Apache the header needs `mod_headers` (`a2enmod headers`). Rules printed by older releases lack the header: print them again and replace the old ones.

The printed rules were checked on Ubuntu 26.04 with nginx 1.28, Apache 2.4 and Caddy 2.6, behind PHP-FPM 8.5: with PHP-FPM stopped, stored pages are still sent and other pages fail. On Apache, the rules go in `public/.htaccess` before Laravel's own. They find the cache folder from `public/` itself, not from the document root, so they also work when the host's web folder is the app's root and a `.htaccess` there forwards requests to `public/`, as on shared hosting ([Installation](/getting-started/installation#shared-hosting)). Rules printed by older releases use the document root and miss in that setup, so print them again. Lynx URLs have no trailing slash by default, as Laravel's, so Laravel's stock "Redirect Trailing Slashes" rule can stay. If you set `lynx.trailing_slash` to `true` (addresses ending with a slash, as ProcessWire writes them), remove that rule: it would send every page to the address without the slash, an extra redirect on each click.

**Before Laravel boots**, for hosts whose web server you cannot change. Add one line to `public/index.php`, anywhere before `bootstrap/app.php` (it loads its own classes, so it works before or after the Composer autoloader):

```php
(require __DIR__.'/../vendor/lynxcms/cache/serve.php')(__DIR__.'/../bootstrap/cache/lynx-cache.php');
```

and compile the settings it reads in your deploy script, after any config change (in this monorepo the file is `packages/cache/serve.php`):

```bash
php artisan lynx:page-cache --compile
```

Without the compiled file the line does nothing.

### How fast

Measured on an Ubuntu 26.04 VM (5 cores, 4 GB) with nginx 1.28, PHP-FPM 8.5 (5 workers, opcache), SQLite and PostgreSQL 18 on the same machine, in production mode without Laravel's config cache, with stored menus and fields loaded on first read. Both databases hold the same workbench content. `ab` ran inside the VM with keep-alive, on the home page (13 KB) and the course list (25 KB); where the two pages differ, both are given (home, then courses).

| Served by | SQLite, 1 client | PostgreSQL, 1 client | SQLite, 10 clients | PostgreSQL, 10 clients |
| --- | --- | --- | --- | --- |
| Full render | 21.1 / 32.3 ms | 40.6 / 52.1 ms | 231 / 140 req/s | 138 / 100 req/s |
| Course list with the list in `@lynxCache` | 20.5 ms | 37.5 ms | 243 req/s | 143 req/s |
| Laravel 404 (startup and one path lookup) | 16.0 ms | 27.0 ms | 301 req/s | 180 req/s |
| PHP middleware, `files` driver | 12.0 / 11.9 ms | 11.9 ms | 374 / 408 req/s | 431 / 426 req/s |
| PHP middleware, `store` driver, database cache store | 13.2 / 12.5 ms | 21.3 / 21.1 ms | 397 req/s | 223 / 194 req/s |
| PHP middleware, `store` driver, file cache store | 11.7 / 19.7 ms | 12.4 / 12.5 ms | 414 / 392 req/s | 392 / 422 req/s |
| `serve.php` before Laravel boots | 0.43 / 0.45 ms | 0.39 ms | 12,300 / 11,600 req/s | 13,300 req/s |
| nginx rules | 0.08 ms | 0.07 / 0.08 ms | 100,000 / 108,000 req/s | 88,000 / 115,000 req/s |

What the numbers mean:

- Without the web server rules or `serve.php`, a stored page is still sent by Laravel. That skips the database and Blade, but booting the framework costs about 10 ms, so a hit is 1.8 to 2.7 times faster than rendering on SQLite and 3.4 to 4.4 times on PostgreSQL. (The course list on the file cache store had a few slow requests in its single client run, which raised its mean; its median was 14 ms.)
- `serve.php` is about 30 times faster than the middleware, and the web server rules about 5 times faster again. Use one of them with the `files` driver whenever you can.
- Stored pages cost the same on both databases, since sending them reads no table. The exception is the `store` driver on a database cache store: on PostgreSQL that one read opens a connection, about 9 ms (see below). Use Redis, Valkey or the file store for it.
- With Octane the framework stays booted between requests, which removes most of the middleware's cost (not measured here).
- A fragment around the course listing cut the render by about 12 ms on SQLite and 15 ms on PostgreSQL.
- Rendered pages saturate the PHP workers first. Stored pages sent by `serve.php` or the web server leave them free for the requests that need PHP.

For what these numbers mean in visitors, see [Server capacity](/deeper/server-capacity). To repeat the test on your server, compare `ab -k -n 1000 -c 10 https://your.site/page` with and without the `lynx_bypass` cookie (`-C lynx_bypass=1`), which sends the request to Laravel and skips the cache.

### Two cores against five

The same test ran on a second VM with 2 cores instead of 5, everything else equal: same host, memory, packages, content, Lynx version and 5 PHP-FPM workers. The VMs ran it one after the other, so they never competed for the host. Times are for 1 client, requests per second for 10 (home, then courses).

| Served by | 5 cores, 1 client | 2 cores, 1 client | 5 cores, 10 clients | 2 cores, 10 clients |
| --- | --- | --- | --- | --- |
| Full render, SQLite | 21.1 / 32.3 ms | 18.5 / 28.1 ms | 231 / 140 req/s | 100 / 70 req/s |
| Full render, PostgreSQL | 40.6 / 52.1 ms | 34.8 / 46.8 ms | 138 / 100 req/s | 64 / 44 req/s |
| Full render, PostgreSQL, persistent connections | 29.8 / 42.0 ms | 26.1 / 35.7 ms | 195 / 134 req/s | 95 / 62 req/s |
| Laravel 404, SQLite | 16.0 ms | 13.9 ms | 301 req/s | 146 req/s |
| PHP middleware, `files` driver | 12.0 / 11.9 ms | 10.7 / 10.8 ms | 374 / 408 req/s | 196 / 195 req/s |
| PHP middleware, `store` driver, database cache store on PostgreSQL | 21.3 / 21.1 ms | 19.3 / 20.6 ms | 223 / 194 req/s | 99 / 96 req/s |
| `serve.php` before Laravel boots | 0.43 / 0.45 ms | 0.32 ms | 12,300 / 11,600 req/s | 8,650 / 8,050 req/s |
| nginx rules | 0.08 ms | 0.05 / 0.07 ms | 100,000 / 108,000 req/s | 77,000 / 84,000 req/s |

What the numbers mean:

- One request runs on one core, so fewer cores don't make a single page slower. The 2 core VM was even 10 to 15% quicker with one client, which is within how much the host's scheduling varies between VMs.
- Under load, everything PHP does falls by 2 to 2.3 times, close to the 2.5 times fewer cores. Renders and the middleware are bound by the CPU, so a server's page rate grows with its cores. With 10 clients a render waited about 100 ms on 2 cores against 43 ms on 5.
- `serve.php` lost about 30% and nginx about 20%. They use so little CPU that `ab`, running on the same VM, takes a good share of it, so the real loss is smaller.
- A 2 core server rendered 64 to 100 pages a second, and served 8,000 a second through `serve.php` and 77,000 through nginx, so on small servers the page cache matters most.

### Rendering on PostgreSQL

A page that is not stored renders 1.6 to 1.9 times slower on PostgreSQL than on SQLite here (2 to 2.5 times before stored menus and fields loaded on first read), for two reasons:

- **Each request opens a connection.** PHP-FPM keeps nothing between requests, and opening a PostgreSQL connection over TCP took 7.75 ms: about 2.4 ms for TLS (Laravel's default `sslmode` is `prefer`) and 5.4 ms for the password check and a new server process. A fresh server process also loads each table's definition the first time it touches it. SQLite opens its file in 0.03 ms.
- **Each query is a round trip.** The home page runs about 80 queries in a fresh process: the registries (languages, templates, fields), the path lookup, the page and its fields, and 62 for the two menus, since every menu field is its own table. Menus are now stored built ([Menus](/modules/menus#stored-menus)), which leaves one query per menu, and pages load only the fields a template reads ([Selectors](/basics/selectors#which-fields-load)): the home page now runs 21 queries. On PostgreSQL each takes 0.2 to 0.6 ms; on SQLite, which runs inside PHP, a few hundredths.

The menu cache, persistent connections and fragments bring the render close to SQLite. The rows were measured as Lynx changed, the last ones with stored menus and fields loaded on first read:

| Full render on PostgreSQL | Home, 1 client | Courses, 1 client | Home, 10 clients | Courses, 10 clients |
| --- | --- | --- | --- | --- |
| Before the menu cache | 76.5 ms | 78.2 ms | 94 req/s | 81 req/s |
| Persistent connections, no menu cache | 54.1 ms | 64.9 ms | 128 req/s | 103 req/s |
| Menus in fragments, no menu cache | 38.9 ms | 51.3 ms | 138 req/s | 104 req/s |
| Fragments and persistent connections | 29.3 ms | 41.1 ms | 214 req/s | 143 req/s |
| Menu cache in the cache store (file store) | 47.5 ms | 58.6 ms | 117 req/s | 94 req/s |
| Menu cache in the cache store, persistent connections | 37.7 ms | 48.5 ms | 163 req/s | 126 req/s |
| Stored menus, fields on first read | 40.9 ms | 54.3 ms | 133 req/s | 97 req/s |
| Stored menus, fields on first read, persistent connections | 30.5 ms | 44.3 ms | 201 req/s | 136 req/s |

**Persistent connections** keep one connection per PHP-FPM worker instead of one per request. Add `options` to the `pgsql` connection in `config/database.php`:

```php
'options' => [PDO::ATTR_PERSISTENT => true],
```

Each worker then holds a connection open, so keep the number of workers (on every server) below PostgreSQL's `max_connections`. A connection pooler (PgBouncer, or the pooled endpoint of a managed database) gives the same saving without that limit.

**Menus in fragments** skip the rest of the menu work on every page. Menu links mark the current page, so the fragment varies by page:

```blade
@lynxCache('top-menu', ['vary' => [$page->id]])
    <x-lynx-menu name="top" />
@endLynxCache
```

## What is stored

Only what any guest would see:

- GET and HEAD requests for a host in `hosts` (the host of `APP_URL` by default), without a bypass cookie.
- Status 200 HTML pages at their own address (`/Courses` is not stored for `/courses`).
- Not for signed in users. Signing in sets the `lynx_bypass` cookie, so web servers send those users to Laravel too.
- Not when the response says `Cache-Control: no-store`, as password protected pages and draft previews do.
- Not when the response sets a cookie other than the session's.
- Not when a [visibility rule](/deeper/visibility#caches-and-audiences) shows this visitor more than a guest sees: its `audience()` names a group, or the rule doesn't implement `VariesByVisitor`, so Lynx can't tell. Such a rule keeps every page out of the cache, so give your rules an `audience()`.
- Anything else a guest sees only for themselves, such as a cart kept in the session, must say so: send the response with `Cache-Control: no-store`, as in `response($view)->header('Cache-Control', 'no-store, private')`.
- Pages holding the visitor's CSRF token (a form, a Livewire component, a layout printing `@livewireScripts`) are stored with a placeholder that a script refreshes. See [Pages with forms](#pages-with-forms).
- Only templates listed in `templates`. All of them by default.

With `LYNX_PAGE_CACHE_DEBUG=true`, responses carry `X-Lynx-Cache: HIT`, `MISS`, or `SKIP; reason=...` with why a page was not stored.

## Template rules

```php
'templates' => [
    '*' => [],
    'course' => ['lifetime' => 3600, 'parameters' => ['page'], 'clear' => ['self', 'parents', 'home']],
],
```

- `lifetime`: seconds a page is kept. 0, the default, keeps it until content changes.
- `parameters`: query parameters that make their own stored copy, such as `page` for pagination. A request with any other parameter is not stored. Tracking parameters (`utm_*`, `gclid`, `fbclid`) are dropped first, so a link from a newsletter gets the stored page.
- `clear`: what a change to a page of this template clears.

A template missing from the list, with no `'*'` entry, is never stored.

Editors with the `page_cache.manage` permission change the rules in the admin, under **Settings > Page cache**, without a deploy. Rules saved there win over the config for the same template, and a template switched off there is not stored even if the config lists it. The screen also shows how many pages are stored per template, clears them, and prints the web server rules. The page editor links to it for pages that are stored.

From code, MCP or the agent, the `page_cache.save_rules` action saves the same rules:

```php
lynx()->run(SavePageCacheRules::class, [
    'lifetime' => 600,
    'templates' => [
        'course' => ['cache' => true, 'lifetime' => 3600, 'parameters' => ['page'], 'clear' => ['self', 'parents']],
        'search' => ['cache' => false],
    ],
]);
```

The first save installs a hidden settings page (`page_cache.install`). What only the server can decide stays in the config: whether the cache is on, the driver, hosts and the edge TTL.

## Clearing

By default any change clears every stored page, since menus, listings and related pages can show any page. A template's `clear` list narrows it:

| Scope | Clears |
| --- | --- |
| `self` | The page, in every language and with every query variant |
| `parents` | The pages above it |
| `children` | The pages directly below it |
| `siblings` | The other pages with the same parent |
| `home` | The home page |
| `all` | Every page (the default) |

Changing a language, a template, a field or site strings always clears every page. Changing Blade files does not clear anything: deploy scripts should clear the cache after a release:

```bash
php artisan lynx:page-cache --clear
```

To have pages ready before the first visitors, warm the cache after clearing it:

```bash
php artisan lynx:page-cache --clear --warm
```

Warming renders every published page with a URL whose template has a cache rule, in every language it is live in, through the app itself (no network requests), so the same rules decide what is stored. It prints how many pages were stored and, for the rest, why not (`csrf-token`, `private`, …). It renders as a visitor with no cookies and no account: visibility rules apply, so password protected pages stay out of the lists and menus it stores, as they would for a guest. It uses the host of `APP_URL`. With the `files` driver on several servers, each server keeps its own copies, so warm on each, or use the `store` driver.

The `page_cache.clear` action does the same from code, MCP or the agent, for every page, some page ids, or one template's pages. It needs the `page_cache.manage` permission.

Pages with a lifetime expire in PHP at once, but web servers keep sending the file until it is deleted. The scheduler runs `lynx:page-cache --prune` every minute while the cache is on, so run `php artisan schedule:run` from cron as Laravel expects.

A page that was rendering while a clear ran is not stored, so a slow request cannot put back what a save just cleared.

### When your own data changes

Lynx clears pages when Lynx content changes. It can't see your app's own tables. If a post page shows comments stored by your app, clear the post when a comment arrives:

<<< @/snippets/modules/ClearPostOnComment.php#listener{php}

`page_cache.clear` checks `page_cache.manage`, as every action checks its permission. A signed-in reader who comments doesn't hold it, and actions run as the signed-in user. So code that clears on a visitor's behalf runs as the system, with `lynx()->actingAs(Actor::system(), ...)`. Requests with nobody signed in run as a visitor, who may only run actions open to everyone, and Artisan commands, queue workers and the scheduler run as `cli`, which may do everything.

For data that changes on a timetable, such as prices imported every hour, give the template's rule a lifetime instead, or clear its pages with `['template' => 'course']` after the import.

## Pages with forms

A form, or any Livewire component, carries the visitor's CSRF token, which no other visitor can use. Such pages are stored with a placeholder instead of the token (token refresh). To leave them out instead:

```dotenv
LYNX_PAGE_CACHE_CSRF=skip
```

or per template, with `'csrf' => 'skip'` in its rule or by unticking "Store pages with forms or Livewire too" in the admin.

With token refresh, the page is stored with a placeholder instead of the token. A small script (`/lynx-cache/refresh-token.js`) asks `/lynx-cache/dynamic` for a fresh token after the page loads and puts it exactly where the placeholder is: `meta` tags, `_token` inputs, Livewire's `data-csrf` and `livewireScriptConfig`. The same request gives the visitor a session and the `XSRF-TOKEN` cookie. Livewire reads the token when it sends a request, and its checksums do not depend on the session, so a stored component works for every visitor. The script is a separate file, so a Content Security Policy allowing `'self'` is enough.

### When visitors arrive without a session

A visitor landing on a stored page has no session yet. Every request the page makes to Laravel while it loads starts one: the refresh request, but also a favicon or an image that Laravel answers, or an API call. The browser keeps whichever session cookie arrives last, so a token fetched by one request can belong to a session the browser no longer has. The refresh script handles this on stored pages:

- Requests that change data and go through `fetch` or `XMLHttpRequest` (Livewire, axios, Precognition, your own `fetch` calls) send the `XSRF-TOKEN` cookie as `X-XSRF-TOKEN`, and leave out a token copied into the page (the `X-CSRF-TOKEN` header, or `_token` in a JSON, form or URL-encoded body). The cookie always comes with the session cookie, so the two always match.
- Plain HTML forms send their `_token` field. When the session changed since that token was fetched, the script fetches the current one before the form goes, then sends it the usual way.

Laravel 13 also accepts a same-site request without checking the token when the browser sends `Sec-Fetch-Site: same-origin`. Browsers send that header only from secure contexts (HTTPS, or `localhost`), so on plain HTTP the token always decides.

### Forms written in JavaScript

Read the token when the request is sent, never when the page loads. A token copied into `x-data` or read in `init()` is the placeholder on a stored page. On stored pages, requests through `fetch` or `XMLHttpRequest` are covered by the cookie as described above, whatever token they carry.

Read it lazily, as Statamic Peak's form handler does. Axios and Laravel Precognition turn header values into text only when they send the request:

```js
this.form = this.$form('post', action, data, {
    headers: {
        'X-CSRF-TOKEN': { toString: () => this.$refs.form.querySelector('[name="_token"]').value },
    },
});
```

Or wait for it:

```js
async submit() {
    const token = await window.lynxCsrfToken();
    await fetch('/enrol', { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new FormData(this.$refs.form) });
}
```

`window.lynxCsrfToken()` and the `lynx:cache-refreshed` event (`event.detail.token`) come with the refresh script. Put `@lynxCsrf` in the layout's `<head>` so they exist on every page, stored or not: it prints `<meta name="csrf-token">` and the script, and on pages that are not stored it hands back the page's own token straight away. A page with no token at all is stored as it is, without the script, and visitors arriving on it get no cookies. If its JavaScript relies only on the `XSRF-TOKEN` cookie, older browsers will be refused; add `@lynxCsrf` and turn token refresh on, so the page is stored with the script and visitors get the cookie.

Axios sends the `XSRF-TOKEN` cookie as a header on its own, so it works once the refresh request has set the cookie.

### Livewire bundled by hand

Livewire's own script starts by itself. If you bundle Livewire and call `Livewire.start()` yourself, start it after the token arrives, so components that send a request on load (lazy loading, `wire:init`) use the fresh token:

```js
if (window.livewireScriptConfig?.csrf === 'lynx-cache-csrf-placeholder') {
    document.addEventListener('lynx:cache-refreshed', () => Livewire.start(), { once: true });
} else {
    Livewire.start();
}
```

### What you trade

- A cached form keeps the time it was first rendered, so the honeypot's "sent too fast" check never triggers on it. The honeypot field and rate limits still work. **If a form relies on the timing check, put it in an island**, which renders it fresh for every visitor, or set its template to `'csrf' => 'skip'`.
- Livewire marks every page with a component `no-store`, to keep stale components out of the back button cache. The page cache recognises those exact headers and ignores them; any other `no-store`, such as a password protected page's, still keeps the page out.

## Islands

An island is a part of a stored page rendered for each visitor: a cart, a greeting, or a form that must be fresh. On a page that may be stored, it prints a placeholder; the refresh script fetches every island of the page in one request, with the token, and puts them in. Signed in users, and pages that are not stored, get the island rendered in place.

```blade
<x-lynx-island view="partials.cart" :with="['compact' => true]">
    <p>Loading your cart…</p>
</x-lynx-island>

<x-lynx-island livewire="lynx-form" :with="['form' => 'newsletter']" />
```

- `view` is a Blade view, given `$page` and your `with` values. `livewire` is a Livewire component, mounted with `with`.
- The slot is shown until the island arrives, and stays if it cannot be loaded.
- The island renders in the page's language, for the visitor asking, so visibility rules and sign ins apply.
- `with` must be plain values (strings, numbers, booleans, arrays of them). The view or component name and its values travel encrypted with the app key, so visitors cannot ask for anything else.
- Livewire and Alpine start components inside islands on their own. If an island is the page's only Livewire component, Livewire's script is still added to the stored page.
- A Livewire component's `@assets` and `@script` blocks work inside islands. The refresh request brings the assets with the islands; the script adds them to `<head>` and waits for scripts to load before it puts the islands in, as Livewire does, so a component's `@script` can use what its `@assets` load. Assets the page already has (the same `src` or `href`) are not loaded twice.
- A page fills at most 20 islands, and `/lynx-cache/dynamic` takes 120 requests a minute per visitor.

A form in an island is rendered for each visitor, so the honeypot's "sent too fast" check works as on an uncached page.

## Fragment cache

For pages that are not stored whole, or parts that cost a lot to render, keep fragments, like ProcessWire's markup cache:

```blade
@lynxCache('main-menu', ['vary' => [$page->id]])
    <x-lynx-menu name="main" />
@endLynxCache

@lynxCache('course-list', ['dependsOn' => ['template:course'], 'ttl' => 3600])
    @foreach (lynx()->pages->find('template=course, sort=title') as $course) … @endforeach
@endLynxCache
```

```php
$html = lynx()->cache()->remember('related-'.$page->id, fn () => view('partials.related', ['page' => $page])->render(), dependsOn: ['page:'.$page->id]);
```

- `dependsOn` names what clears the fragment: `page:{id}` or `template:{name}`. Without it, any content change clears it. Changing a language, a template, a field or site strings clears every fragment.
- `ttl` is a lifetime in seconds; without one the fragment stays until what it depends on changes (or `fragments.ttl`).
- Each language keeps its own copy, and so does each `vary` value. Vary by `$page->id` for anything that marks the current page, such as menus, or every page shows the link that was current when the fragment was stored. Guests holding the `lynx_page_password` cookie (unlocked pages) get their own copies, since they may see more.
- Signed in users always get a fresh render, so editors never see a cached draft or someone else's view.
- A fragment holding the visitor's CSRF token is not stored. Put forms outside fragments, or in an island.

The admin's page cache screen lists stored fragments: their name, language, what clears them, size and age. **View** shows what one holds, as source and as a preview without the site's styles or scripts. Clear one copy, every copy of a name, or all of them. From code, MCP or the agent, `page_cache.clear` with `fragments: true` or `fragment: "main-menu"` does the same.

## Edge caches

`edge_ttl` lets an edge cache, such as Laravel Cloud's Cloudflare edge, keep stored pages for that many seconds:

```php
'edge_ttl' => 30,
```

Laravel Cloud clears the edge on deploy but cannot clear one page, so a change shows at the edge after at most that many seconds. Keep it short, or leave it at 0.
