---
title: Introduction
description: What Lynx is, how it fits into a Laravel app, and the packages it is made of.
---

# Introduction

Lynx is a content management system that you add to a Laravel application. It gives your app a tree of pages, fields you define without writing migrations, an admin where editors work, and built-in support for sites in several languages.

If you know Laravel's routing, Blade and Eloquent, you already know most of what you need. Lynx adds a few ideas of its own, which the rest of this guide introduces one at a time.

> [!NOTE]
> Lynx is in active development and has not reached 1.0. Its APIs may still change between releases, and the [roadmap](/prologue/roadmap) lists what is planned.

## Why Lynx?

Most Laravel content sites start the same way: a `posts` table, a migration, a model, a Filament or Nova resource for editing, and later a second language that means new columns or a translations table. Lynx replaces that groundwork:

- **Content types without migrations.** You describe a *template* (say, `post`) and its *fields* (`title`, `summary`, `body`) in the admin, in PHP or in YAML. Lynx creates the tables and gives you an editing screen for it.
- **Languages from the start.** A language is a row, not a column. Adding Portuguese to a running site takes one step, every translatable field falls back to another language when a translation is missing, and each page gets a translated URL such as `/pt/blogue/ola-mundo`.
- **Pages in a tree.** Pages have parents and children, like folders, so URLs, menus, breadcrumbs and permissions follow the structure editors see.
- **One way to change content.** Saving, moving and translating pages, or adding a field, are *actions*. The admin, your code, Artisan commands, the MCP server and the built-in AI agent all run the same actions, so validation, permissions and the activity log always apply.

## How Lynx fits into your app

Lynx is a set of Composer packages, not a separate application. After installing it:

- Your routes still come first. Lynx registers one catch-all route that serves the pages of the tree for any URL your app does not handle.
- Pages render with Blade. A page that uses the `post` template renders `resources/views/templates/post.blade.php`, which receives the page as `$page`.
- You can read content anywhere: in a controller, a Livewire component or a job, `lynx()->pages->find('template=post, limit=10')` returns pages.
- Your app keeps its own database tables, users, queues and deployment. Lynx adds tables that start with `lynx_`.

## An admin for editors, and optional

The `lynxcms/admin` package gives editors a place to work that needs no technical knowledge. They see the site as a tree of pages they can open, drag to reorder and move; a page editor with the fields laid out the way you arranged them, in plain labels, with a tab per language; drafts they can preview before publishing; publishing on a schedule; a history of every save they can compare and restore; and a trash to recover from mistakes. Developer details, such as template names and field types, are shown only to superusers, and a site with one language never mentions translations.

Lynx runs without the admin. With only `lynxcms/core`, you define templates and fields in PHP or in YAML files, write content from code, seeders, imports or [content packages](/deeper/schema-and-content), and let AI tools work through the [MCP server](/ai/). Your app can build its own editing screens on the same API, and every rule, such as permissions and validation, still applies, because it lives in the [actions](/getting-started/concepts#actions), not in the admin.

## Livewire

Pages render with Blade, so a template can use any [Livewire](https://livewire.laravel.com) component of your app, such as a newsletter form or a search filter. With a [`component` field](/basics/field-types#component), editors place components on pages themselves and fill in their settings. See [Views](/basics/views#livewire-components).

The admin is built with Livewire 4 too, so you can add screens of your own to it the same way you build the rest of your app ([Extending the Admin](/admin/extending)).

## The packages

Only `lynxcms/core` is required. Add the others when a site needs them; each one has its own page in this guide.

| Package | What it adds |
| --- | --- |
| `lynxcms/core` | Languages, fields, templates, pages, selectors, actions, routing and Blade helpers. |
| `lynxcms/admin` | The admin at `/lynx`: page tree, page editor, fields, templates, languages, roles and users. |
| `lynxcms/menus` | Menus that editors build, and the `<x-lynx-menu>` component. |
| `lynxcms/media` | Image and file fields, with resizing and focus points. |
| `lynxcms/forms` | Forms defined in PHP or built in the admin, with an inbox for submissions. |
| `lynxcms/seo` | Titles, descriptions, social cards and structured data for each page. |
| `lynxcms/sitemap` | An XML sitemap with every language. |
| `lynxcms/cache` | A page cache that stores pages for guests and sends them again without rendering, or without PHP at all with web server rules. |
| `lynxcms/page-password` | Pages that ask visitors for a password. |
| `lynxcms/static-site` | Exports the site as static files. |
| `lynxcms/mcp` | An MCP server, so AI tools such as Claude can work with the site. |
| `lynxcms/ai` | A built-in AI agent and machine translation. |
| `lynxcms/contracts` | Interfaces the packages share. You do not install it yourself. |

## Where to go next

- [Installation](/getting-started/installation) adds Lynx to a new or existing Laravel app.
- [Your First Site](/getting-started/first-site) builds a small blog in two languages, step by step.
- [Core Concepts](/getting-started/concepts) explains pages, templates, fields, languages and actions in more depth.
