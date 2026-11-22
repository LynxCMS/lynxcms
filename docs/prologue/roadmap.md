---
title: Roadmap
description: Ideas we may build later, with the open questions behind them.
order: 9
---

# Roadmap

These are ideas, not promises. Each needs its own decision record before work starts.

## Paid modules

### Translate

`lynxcms/translate`: machine translation for the Lynx CMS admin (paid module). It is coming in a future release.

Editors press **Translate** in the page editor, or under one field, choose the languages and the service, and the empty fields fill in the page's [draft](/basics/publishing#drafts). Visitors see nothing until someone reviews the draft and publishes it.

- **Services:** DeepL, a local model with an OpenAI-compatible API (Ollama, LM Studio, llama.cpp or vLLM), or a cloud model through `lynxcms/ai`.
- **Control over cost and wording:** the dialog counts the texts and characters before anything is sent, a monthly budget in characters stops translations past it, and a glossary per language pair keeps terms the same.
- **Text a person wrote is never replaced.** Translating a page also fills its picture descriptions, form options and template texts where they are empty. Site texts are shown for review before they are saved.
- **Under each field:** a badge says when a machine wrote the text, or when its source has changed since.
- **Many pages at once:** every page, one template, or a branch, each into its own draft, in the background with a queue worker.

It will need Lynx 0.1.41 or later with the admin, and a site with at least two languages. It uses only Lynx's public API (classes marked `@api`).

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
