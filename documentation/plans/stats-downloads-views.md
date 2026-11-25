# lynx-homepage: download counts, page views and a decayed Popular sort

Status: built on 2026-11-25 in lynx-homepage (LynxCMS/lynx-corporate-starter#4). Kept with Lynx's plans because it may become a `lynxcms/stats` module.

Changes while building:

- **No WAL.** The demo's hourly reset runs `migrate:fresh`, which empties the SQLite file; that breaks WAL's side files under open connections (the reset test failed with "database is locked"). Only `busy_timeout` 5000 is set.
- **No limiter on the download route.** Counting once per link already bounds it, and the links come from the gated form, so a per-address limiter would only add an address to the cache.
- **A seeder bug fixed on the way.** `SavePageCacheRules` replaces every stored rule, and `SearchAndBookmarksSeeder` saved only the search rule after `SiteFeaturesSeeder`, so the home and download rules were lost. Both now save one shared list.

## Context

lynx-homepage (~/Herd/lynx-homepage, the Arcturus Systems demo on the local Lynx monorepo) has a downloads library at `/resources/` (templates `downloads` and `download`, a private `document` files field with one PDF per language, gated by a Livewire form in an island). It counts nothing: files go out through Lynx's signed route, which fires no event, and both download pages are page-cached, so no server code runs on most views.

The owner wants, decided over several rounds: real (non-bot) download and view counts; a Popular sort for downloads that decays over time, with pagination; counts on cards, in the page editor and on the admin home (most visited pages, most downloaded files); about 500 seeded downloads with realistic, varied history; counts from outside the site later; tracking of other interactions (a hero click, opening a details section); a path to a separate stats database under heavier traffic; no personal data; easy to repeat on a future site (app code now, a Lynx module later).

An independent review (2026-11-25) found the first draft overengineered and with real bugs (session cookies from the beacon, an HttpOnly cookie read from JavaScript, a throttle that could block downloads, a stored score that drifts, salted IP hashes that are still personal data for a day). This version builds the core well and leaves the extras as hooks, each listed under Deferred.

## Shape

- **`app/Stats/`**: generic, reads only `@api` Lynx code, knows nothing about Arcturus (an arch test checks it). Moving it into a `lynxcms/stats` module later means renaming the namespace, a service provider and an ADR.
- **Homepage wiring** outside it: `Document`, `DownloadForm`, `SendDownloadLink`, the `/resources/` listing, cards, the cache rule and the seeder of 500 items.

Reuse: `database/seeders/Support/Downloads.php::create()` (a download page with a PDF per language), `app/Downloads/Document.php` (the file for a language), the beacon pattern of `SearchHitController` + `resources/js/search.js`. The search `Popularity` stays as it is.

## 1. Bot filter

- `jaybizzle/crawler-detect` (3.9M downloads a month, pure PHP), `^1.4.2`, installed at 1.4.2 (1.4.3 is under two weeks old). No Laravel wrapper.
- `App\Stats\RealVisitor::is(Request)`: false for crawler agents, an empty agent, HEAD and prefetches (`Sec-Purpose`/`Purpose: prefetch`).
- `SearchHitController` uses it, and its rate-limit key drops the plain IP it holds today (`search-hit:{ip}:{id}`).

## 2. Storage

- SQLite settings for concurrent writes: `journal_mode` WAL and `busy_timeout` 5000 on the default connection (`config/database.php`, today null).
- One migration, `stat_counts` (page_id, event, subject, source, day, count), primary key (page_id, event, subject, source, day), index (event, day). Daily totals, never a row per visitor.
  - `event`: `view`, `download`, or a site event from the allow-list (section 5).
  - `subject`: `''`, or for downloads the file's language code, or a label the site chose (`[A-Za-z0-9._-]{1,64}`, anything else dropped).
  - `source`: `site` (the default; a hook for imports later).
- `stat_links` (hash, expires_at): a download link already counted (section 3).
- `App\Stats\PageStats`: `record(int $page, string $event, int $count = 1, string $subject = '', ?CarbonInterface $day = null, string $source = 'site')` as an upsert (`insertOrIgnore` then `increment`), `totals(array $ids, string $event, int $days)`, `daily(array $ids, string $event, int $days)`.
- Every query goes through `PageStats::connection()` (`config('stats.connection')`, the default connection when null), and the migration's `getConnection()` reads it. That is the whole separate-database support for now.

## 3. Downloads, counted when fetched

- `App\Stats\DownloadLinks::for(Pagefile $file, CarbonInterface $expires, string $subject)`: `URL::temporarySignedRoute('stats.file', ...)` to `GET /stats/file/{page}/{file}`.
- `DownloadFileController` (route before Lynx's catch-all route):
  1. The signature must be valid (403) and the page published and holding the file (404).
  2. A real visitor, on the link's first use, records one download: the link's hash goes into `stat_links` until it expires, so email link scanners (which send browser user agents) and repeat clicks count once per link, with no IP involved.
  3. Always redirects to Lynx's signed URL for 5 minutes (`Pagefile::temporaryUrl`), so a PDF viewer's range requests never come back here. No throttle stands in front of the redirect; a named limiter only stops recording past 30 a minute.
- `Document::link($expires)` calls it with the language as subject; `DownloadForm::afterSent()` and `SendDownloadLink` use it, keeping their lifetimes from `config/downloads.php`. A daily cleanup deletes expired `stat_links` rows.

## 4. Page views

- `<x-stats-beacon :page="$page" />` in the layout: a small inline script with the page id (the same for every visitor, so cached pages keep it). After one second visible, `navigator.sendBeacon('/stats/hit', {page, event: 'view'})`; not when prerendering.
- `POST /stats/hit` is **stateless**: registered without the `web` group (no session, no cookies, no CSRF token, so no exemption needed). It accepts only `Sec-Fetch-Site: same-origin` (or a matching `Origin`), a real visitor, a published page with a URL, and a known event. Signed-in editors are skipped on the server through the `lynx_bypass` cookie (HttpOnly, so JavaScript can't read it). Always 204.
- Views count every visit that passes the filters; no per-visitor dedupe, so no IP is kept anywhere.

## 5. Site events

- `config/stats.php` `events`: names the site counts besides `view` and `download`, such as `hero-cta`, `details-open`. Unknown names are ignored.
- The beacon script counts clicks on `[data-stats-event]` and openings of `<details data-stats-event>` (a capture listener, as `toggle` doesn't bubble), with an optional `data-stats-subject`.
- Same endpoint, same filters as views. The homepage gets one example: the home hero's main button.

## 6. Ranking and the `/resources/` listing

- `App\Stats\Ranking::ids(array $ids, string $event)`: orders ids by Σ count × 0.5^(age in days / half-life) over the last 8 half-lives (half-life in `config/stats.php`, 7 days), computed in PHP from `stat_counts`; ids without counts keep their given order after the others. No stored score, so the half-life can change and imports or corrections stay consistent.
- `App\Downloads\DownloadListing`: the ordered id list of published downloads (newest first as the base order), cached 15 minutes; `popular` (default) ranks it, `newest` and `title` use selectors with `start=`/`limit=`. 24 per page; pages loaded with `lynx()->pages->find('id=...')` in order.
- `templates/downloads.blade.php`: its own listing with sort links (`rel="nofollow"`) and a pager; a canonical link to the unsorted first page. An unknown `sort` or a page out of range answers 404, so junk never reaches the cache.
- Page cache rule for `downloads`: `parameters => ['sort', 'page']`, `lifetime => 900`, and `max_variants` raised for this site to cover 3 sorts × the pages (about 25 at 500 items).

## 7. Counts on cards, in the editor and on the admin home

- Cards: "1.2K downloads" (`Number::abbreviate` with the locale, `trans_choice`), totals from one `PageStats::totals` query per listing page. Cards outside the listing (home sections cached with `@lynxCache`) show no count.
- Page editor header (`AdminSlots::EDITOR_HEADER`): one line, "N views · M downloads, 30 days".
- Admin home (`HomeScreen`, `@api`):
  - **Most visited pages** and **Most downloaded files**: plain Blade panels with a view composer, inside `x-lynx-admin::home.section` and `home.row` as `home/recent.blade.php` does; top 10 over 30 days, each row with its count and a bar for its share of the first (BlatUI's meter idea, an inline width).
  - One stat: views today.
  - Cached 5 minutes. Only the admin's own components and classes, since its CSS is built in advance.
- Wording in `lang/en/stats.php` and `lang/pt/stats.php`.

## 8. Seeding

- `php artisan stats:fake-history {selector} {--days=90} {--seed=}`: daily views and downloads that look real, starting from each page's `published_on` field (a launch bump fading over 2 to 3 weeks; a long-tailed appeal; steady, rising, fading, old-popular or recent-spike shapes; weekends at about 40%; a holiday dip; a few newsletter days; Poisson noise). Repeatable with `--seed`. Clears the page cache.
- `php artisan site:seed-downloads {--count=500}`: items through `Downloads::create`, in en, pt or both, `published_on` spread over two years, weighted toward recent months, clustered in release weeks, a dozen in the last week; then `stats:fake-history "template=download"`.
- Both refuse production without `--force`. The hourly demo reset (`migrate:fresh` + `DatabaseSeeder`) adds about 60 items with history, so the public demo's listing and widgets aren't empty.

## 9. Privacy

- No personal data is stored: daily totals only, no IP, user agent, session, user or referrer. The download count never refers to the lead form's submission.
- No cookies from the beacon (stateless route) or the download route's counting.
- Addresses are still used briefly: Laravel's rate limiters key by IP for a minute, and the web server logs requests. The privacy page (Legal seeder, en and pt) says so: what is counted, that no personal data is stored, and that addresses are used briefly to stop abuse.

## Deferred, with hooks in place

- **Imports from outside the site** (HTTP API with a token, CSV command, queued job, replace a day): `PageStats::record(..., source:)` is the hook.
- **A separate stats database**: `config('stats.connection')` is the hook; tests would need `$connectionsToTransact`.
- **Compaction of old rows, buffered writes in Redis**: about 365,000 rows a year at 500 pages; add when measured.
- **Unique visitors** (daily salt): views count visits for now.
- **Trending, other periods, sparklines and changes against the previous period** in the home widgets.
- **Global Privacy Control**: nothing personal is processed, so there is nothing to opt out of.
- **A Lynx module** once a second site needs it.

## Commits

lynx-homepage's local `composer.json` and `composer.lock` keep the path repository and are never committed. Add `crawler-detect` locally, and commit the dependency from a clean clone in the scratchpad (as the 0.1.59 update was). The code is committed from the local checkout without those two files.

## Tests (Pest)

- RealVisitor: Googlebot, an empty agent, HEAD and prefetch refused; Chrome and Safari pass. SearchHitController's key holds no IP.
- Download link: unsigned 403; counts once per link however often opened; a crawler isn't counted but still redirected; redirects to a Lynx signed URL; never 429; expired links are cleaned.
- `/stats/hit`: 204 always; no `Set-Cookie`; cross-site requests, bots, unknown events, unpublished pages and signed-in editors aren't counted; `view` and an allowed event are.
- Ranking: recent counts outrank more, older ones; uncounted ids keep their order; changing the half-life reorders without migrations.
- Listing: each sort and page; unknown sort and out-of-range page 404; cache rule parameters; cards show abbreviated counts.
- Editor line and home panels render counts and empty states (data hooks, no page wording).
- Seeding: history starts at `published_on`; weekends lower; repeatable with `--seed` (loose bounds); production refused.
- Privacy: after a view, a download and an event, no table holds an IP or user agent; `app/Stats` uses nothing from the rest of the app.

## Verification

- `vendor/bin/pest`, `vendor/bin/phpstan` (level 8), Pint.
- The owner runs `php artisan site:seed-downloads` locally, then checks `/resources/` (Popular, Newest, A to Z, page 2), a download page's editor line, the admin home panels, a real download from the form (counted once, again from the email link not), and a view from a private window; `curl -A Googlebot` on a download link isn't counted.
