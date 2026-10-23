---
title: SEO
description: Chapter 5 of the training site guide. Titles and descriptions, Course, Event, Place and FAQ structured data, filtered lists and the sitemap.
---

# SEO

A training school lives on search: people look for "pilates instructor course Lisbon", and Google can show a course with its price, or a session with its date and place, right in the results. This chapter gives every page good tags and the structured data search engines read for those rich results.

## Tags on every page

The layout's `@seo($page, $seo)` (from chapter 3) prints the title, description, canonical link, robots rule, share cards for social networks, hreflang links and the JSON-LD graph. Values come from the page itself, then from the site's settings, so pages look right before anyone writes SEO texts.

Turn the SEO fields on for the templates that matter, and fill in the site's settings:

<<< @/snippets/training/database/seeders/AtlasSeoSeeder.php#settings{php}

Each of those templates gets an **SEO** tab in the page editor, where staff can write a search title and description, choose a share image or hide a page from search. Left empty, the title is the page's title in the pattern (*Sports nutrition | Atlas Training*), and the description is the page's summary. The **SEO** screen's **Audit** tab lists pages with no description, duplicate titles or titles over 60 characters.

## Structured data

Map each template to a [schema.org](https://schema.org) type, saying where each property comes from: a field, `@title`, `@url`, `@organization` (the school, from the settings) or a fixed value after `=`:

<<< @/snippets/training/database/seeders/AtlasSeoSeeder.php#structured-data{php}

What that gives each page:

| Page | JSON-LD | Search engines can show |
| --- | --- | --- |
| A course | `Course`, provided by the school, with an offer in euros | The course and its price |
| A session | `Event` with its start, status and an offer, at a `Place` | The date, the studio, and whether it is cancelled |
| A studio | `Place` with its address | The address |
| The home page | `FAQPage` from the questions repeater | The questions and answers |

The session's `location` is a page reference to a studio. Because the `location` template is mapped too, the studio appears once in the graph and the event links to it. `availability` is a choice whose values (`scheduled`, `cancelled`) are schema.org's event statuses, so a cancelled session is marked cancelled in search the moment staff change it.

Every page also gets the site, the school and its breadcrumbs. Check a page with Google's Rich Results Test, or ask Lynx: `lynx()->seo($page)->jsonLd`, or the `seo_inspect` MCP tool.

These mappings are actions, so the owner's AI assistant can make them too (`seo_map_template`), and a mapping that would make broken data is refused, in code as in the admin's **Structured data** tab.

## Filtered lists

`/courses?level=beginner` shows the same courses as part of `/courses`. Search engines should index the catalogue once, so the catalogue's view passes overrides to the layout when a filter is set:

```blade
<x-layouts.site :page="$page" :seo="isset($filters['level']) || isset($filters['q']) ? ['robots' => 'noindex, follow', 'canonical' => $page->url] : []">
```

The filtered list asks not to be indexed, still lets crawlers follow its links to the courses, and points them at the full catalogue.

## The sitemap

With `lynxcms/sitemap` installed, `/sitemap.xml` lists every published page in both languages, with the other language as an alternate, and `robots.txt` names it. It updates itself when pages change. Submit it in Google Search Console once the site is live.

## What you get

- **The client:** courses and dates that stand out in search, a checklist of pages to improve, and SEO texts they edit per page and per language.
- **You:** tags and structured data from configuration, not template code; mappings that are validated and versioned with the site; and overrides for the few pages that need them.
