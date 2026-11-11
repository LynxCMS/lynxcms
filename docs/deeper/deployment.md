---
title: Deployment
description: The settings that make rendered pages fast on a production server, and how many visitors the server can take.
---

# Deployment

A few settings outside Lynx decide much of how fast a server renders pages. With the four below and Lynx 0.1.30, the corporate starter's home page renders in less than 40% of the time it took before, and the server takes six times as many requests a second. This page shows those settings, then how many visitors a small server can take.

## Performance

These numbers come from the corporate starter (`LynxCMS/lynx-corporate-starter`): a home page built from sections, with search, a newsletter form and 74 pages in two languages. It ran on the same machine as the page cache's [How fast](/modules/page-cache#how-fast) test: an Ubuntu 26.04 VM with 5 cores and 4 GB, nginx 1.28, PHP-FPM 8.5 with 5 workers, SQLite or PostgreSQL 18, and `ab` inside the VM. The page cache was off, so every request rendered.

### Keep sessions in files

A session is where Laravel keeps what it knows about a visitor between requests, such as the form token or who signed in. A new Laravel app keeps sessions in the database, so every page a visitor opens writes a row. SQLite lets one write happen at a time, so with many visitors at once each page waits for the writes before it. Keep sessions in files instead, in `.env`:

```dotenv
SESSION_DRIVER=file
```

On SQLite, with 10 requests at once, that took the home page from 34 to 149 requests a second and a product list from 34 to 198, and each page came out 16 to 20 ms sooner even with one visitor:

| Corporate starter on SQLite | Home, 1 client | Product list, 1 client | Home, 10 clients | Product list, 10 clients |
| --- | --- | --- | --- | --- |
| Sessions in the database | 52.7 ms | 45.9 ms | 34 req/s | 34 req/s |
| Sessions in the database, `optimize` | 49.2 ms | 45.2 ms | 34 req/s | 31 req/s |
| Sessions in files, `optimize` | 33.4 ms | 25.8 ms | 149 req/s | 198 req/s |

PostgreSQL lets many writes happen at once, so there a session in the database costs a query, not a queue: the same home page reached 72 requests a second with sessions in the database. Redis (`SESSION_DRIVER=redis`) works on either. When several web servers share the traffic, use Redis or the database, since each server only sees its own files.

### Cache the configuration

`php artisan optimize` keeps Laravel's configuration, routes and events in files that load at once, and halves how long Laravel takes to start: 9.4 ms before, 4.8 ms after, measured on Laravel's `/up` route. Run it after every deploy, and again after changing `.env`, since the cached configuration no longer reads it.

### Keep the cache in files or Redis

The [fragment cache](/modules/page-cache#fragment-cache) and the SEO settings read Laravel's cache store on every render, and a new app keeps that in the database too, so each read is a query. With the cache in files (`CACHE_STORE=file`), the home page ran 47 queries instead of 68, and rendered in 43.8 ms instead of 49.2 on PostgreSQL. Use Redis when several web servers share the site.

### Cache the expensive parts

A section that lists other pages costs queries for every page it shows. Keep it in a [fragment](/modules/page-cache#fragment-cache), so it renders once and again only after content changes. The corporate starter keeps each card section and the news headlines that way, which halved its home page.

A form near the bottom of a page can wait until the visitor scrolls to it: `<x-lynx-form :form="$page->form" lazy />` renders it in a request of its own ([Forms](/modules/forms#building-forms-in-the-admin)).

Pages sent from the [page cache](/modules/page-cache) skip all of this, so it matters for the pages the cache can't keep: signed in visitors, search results and the first request after a change.

### What each step gave

The home page rendered inside PHP, without the web server, as Lynx changed and the starter added each step:

| Corporate starter home page | SQLite | PostgreSQL | Queries |
| --- | --- | --- | --- |
| Lynx 0.1.29 | 58.2 ms | 103.1 ms | 129 |
| Lynx 0.1.30 | 50.0 ms | 84.3 ms | 73 |
| Sections and headlines in fragments, form loaded lazily | 26.6 ms | 49.2 ms | 68 |
| Cache store in files | 26.0 ms | 43.8 ms | 47 |

Over HTTP on PostgreSQL with 10 requests at once, the same steps took the home page from 45 to 72 requests a second, and from 54 to 94 with [persistent connections](/modules/page-cache#rendering-on-postgresql).

## Server capacity

How many visitors a server can take depends on how many of its pages are rendered and how many are sent from the [page cache](/modules/page-cache). This guide turns the load test in [How fast](/modules/page-cache#how-fast) and [Two cores against five](/modules/page-cache#two-cores-against-five) into visitor numbers for a 2 core server with 4 GB of memory. They are estimates from those measurements, not from real traffic.

### Visitors and requests

Visitors don't send requests all the time. They read a page for 30 to 60 seconds before opening the next one. So one request a second serves about 30 to 60 people browsing at the same moment, and 10 requests in flight at once (`ab -c 10`) means far more than 10 visitors.

### A 2 core server

The safe rate below is about 60% of the measured peak, to leave room for spikes, scheduled tasks and the admin. The visitor counts assume one page every 30 seconds.

| Pages sent by | Measured peak | Safe rate | People browsing at once |
| --- | --- | --- | --- |
| Full render, PostgreSQL | 44 to 64 req/s | about 30 req/s | about 900 |
| Full render, PostgreSQL, persistent connections | 62 to 95 req/s | about 45 req/s | about 1,300 |
| Full render, SQLite | 70 to 100 req/s | about 50 req/s | about 1,500 |
| Page cache, `serve.php` | 8,000 req/s | limited by the network | tens of thousands |
| Page cache, nginx rules | 77,000 req/s | limited by the network | tens of thousands |

With the page cache, the processor is no longer the limit. A 13 KB page over a 1 Gbps link is about 9,000 pages a second, and far fewer once images are counted, so the bandwidth of the host or a CDN in front of it decides.

Real sites mix both. When 90% of page views come from the cache, PHP renders only one in ten. A server that renders 30 pages a second then takes about 300 page views a second, around 10,000 people browsing at once. Traffic is uneven over a day, so that is roughly 1 to 3 million page views a day.

### Sites that fit

- Company and brochure sites, in one language or many.
- Blogs, news and magazines. A sudden spike on one article is sent from the cache.
- Catalogs and course listings.
- Landing pages for campaigns.

### Where to be careful

- **Many signed in visitors**, such as a members' area or an intranet. Their pages are always rendered, so plan with the full render rows: about 900 people browsing at once on PostgreSQL. A few dozen editors in the admin are no problem.
- **Search, filters and forms on every page.** Each of those requests renders. [Islands](/modules/page-cache#islands) and the [fragment cache](/modules/page-cache#fragment-cache) keep the rest of the page cached or cheaper.
- **Spikes on pages that aren't cached**, such as a newsletter linking to a search page. On 2 cores, response times climb fast: about 100 ms with 10 requests at once, and more beyond that.
- **Clearing.** By default a change clears the whole site, so on a busy site each save sends all traffic to PHP for a moment. [Template rules](/modules/page-cache#template-rules) and warming soften it.

### What the estimate leaves out

- The workbench pages are small, with about 20 queries each. A real theme with more fields and listings renders slower, and the rendered rows shrink by as much: the corporate starter's home page, a page of sections with about 70 queries, took 33 ms against the workbench's 21 ms, with the settings in [Performance](#performance).
- The test ran on a local network without TLS or internet latency, with `ab` on the same machine.
- PostgreSQL, PHP and nginx shared the 2 cores. A managed database frees processor time but adds a network round trip to every query.
- 4 GB of memory was plenty: 5 PHP-FPM workers and PostgreSQL used well under 1 GB.

### Measuring your own site

Load the heaviest page type of the site and compare a rendered request with a cached one:

```bash
ab -k -n 1000 -c 10 -C lynx_bypass=1 https://your.site/page   # rendered
ab -k -n 1000 -c 10 https://your.site/page                     # from the cache
```

Take about 60% of the requests per second as a safe rate, then multiply by the seconds between page views (30 is a fair default) for the number of people browsing at once.
