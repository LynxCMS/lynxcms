---
title: Roadmap
description: Ideas we may build later, with the open questions behind them.
order: 9
---

# Roadmap

These are ideas, not promises. Each needs its own decision record before work starts.

## Security follow-ups

Smaller hardening left from the October 2026 review, none of them exploitable on a default install:

- The agent's plan mode keeps one database transaction open for the whole conversation, which locks SQLite for other writers. Recording steps and running them in a short transaction on apply would avoid it.
- A few admin components keep properties a browser could change unlocked, such as the page table input's columns.
- Page passwords are rate limited per address only; a limit per page and account would slow distributed guessing.
- Form file fields without an extensions list accept any file type; a default list would be safer.
- Invitation tokens share the password reset table; their own table would keep the two apart.

## Template strings

### Page overrides of site strings

A site string (`scope: 'site'`) would stay one text for the whole site, and any page could override it in its **Texts** tab. Reading would go from the page's own edit, to the site edit, to the text in the code, along the language chain at each step. The other pages keep inheriting, so a footer note could change on three courses only.

Open question: site edits are stored on the home page, so the home page could not hold an override of its own. Supporting that means storing site edits apart from any page.

### Edits per template

A text changed once for every page of a template (all courses), between the site text and a page's own edit. This needs edits stored per template, a new level next to pages and the site.

## Content model

- Repeaters inside repeaters. Items use depth for nesting today.
- Sorting pages by a repeater or matrix subfield.

## Forms

- Cloudflare Turnstile as an extra spam check.

## Media

- A central media library, shared by pages, next to the per page files.
- Image sizes made in a queue instead of on first request.

## Page cache

- A Laravel Cloud check before release: a staging environment with two replicas, the `store` driver and Octane. Save a page and confirm both replicas send the new HTML, measure a hit with Octane, then turn on `edge_ttl` and confirm the edge keeps pages for that long.

## Static site export

- Export only what changed since the last export, from `ContentChanged`.
- Pagination in the path (`/news/page/2`), so paginated listings export every page.

## Admin

- The admin interface in the editor's language. It is in English today; field and template labels already follow the editor's language.
