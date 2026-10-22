---
title: Server capacity
description: How many visitors a small server can take, with and without the page cache.
order: 10
---

# Server capacity

How many visitors a server can take depends on how many of its pages are rendered and how many are sent from the [page cache](/docs/front-end/page-cache). This guide turns the load test in [How fast](/docs/front-end/page-cache#how-fast) and [Two cores against five](/docs/front-end/page-cache#two-cores-against-five) into visitor numbers for a 2 core server with 4 GB of memory. They are estimates from those measurements, not from real traffic.

## Visitors and requests

Visitors don't send requests all the time. They read a page for 30 to 60 seconds before opening the next one. So one request a second serves about 30 to 60 people browsing at the same moment, and 10 requests in flight at once (`ab -c 10`) means far more than 10 visitors.

## A 2 core server

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

## Sites that fit

- Company and brochure sites, in one language or many.
- Blogs, news and magazines. A sudden spike on one article is sent from the cache.
- Catalogues and course listings.
- Landing pages for campaigns.

## Where to be careful

- **Many signed in visitors**, such as a members' area or an intranet. Their pages are always rendered, so plan with the full render rows: about 900 people browsing at once on PostgreSQL. A few dozen editors in the admin are no problem.
- **Search, filters and forms on every page.** Each of those requests renders. [Islands](/docs/front-end/page-cache#islands) and the [fragment cache](/docs/front-end/page-cache#fragment-cache) keep the rest of the page cached or cheaper.
- **Spikes on pages that aren't cached**, such as a newsletter linking to a search page. On 2 cores, response times climb fast: about 100 ms with 10 requests at once, and more beyond that.
- **Clearing.** By default a change clears the whole site, so on a busy site each save sends all traffic to PHP for a moment. [Template rules](/docs/front-end/page-cache#template-rules) and warming soften it.

## What the estimate leaves out

- The workbench pages are small, with about 20 queries each. A real theme with more fields and listings may render 2 to 3 times slower, and the rendered rows shrink by as much.
- The test ran on a local network without TLS or internet latency, with `ab` on the same machine.
- PostgreSQL, PHP and nginx shared the 2 cores. A managed database frees processor time but adds a network round trip to every query.
- 4 GB of memory was plenty: 5 PHP-FPM workers and PostgreSQL used well under 1 GB.

## Measuring your own site

Load the heaviest page type of the site and compare a rendered request with a cached one:

```bash
ab -k -n 1000 -c 10 -C lynx_bypass=1 https://your.site/page   # rendered
ab -k -n 1000 -c 10 https://your.site/page                     # from the cache
```

Take about 60% of the requests per second as a safe rate, then multiply by the seconds between page views (30 is a fair default) for the number of people browsing at once.
