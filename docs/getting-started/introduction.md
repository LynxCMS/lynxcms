---
title: Introduction
description: What Lynx is, how it fits into a Laravel app, and the packages it is made of.
---

# Introduction

Lynx is a content management system built on Laravel. It can run as a new Laravel app whose only job is the site. It can also be added to a Laravel application you already have. Lynx gives your app a tree of pages, fields you define without writing migrations, an admin for editors, and support for sites in several languages.

A little Laravel is enough to start. If you have written a route and a Blade view, you know most of what you need. Lynx adds a few ideas of its own, and the rest of this guide introduces them one at a time.

> [!TIP]
> The [live demo](https://demo-corporate.lynxcms.org/) shows a finished site. It is the website of an invented software company, in English and Portuguese. Its code is a starter you can install in about five minutes, admin included: [LynxCMS/lynx-corporate-starter](https://github.com/LynxCMS/lynx-corporate-starter).

> [!NOTE]
> Lynx is in active development and has not reached 1.0. Its APIs may still change between releases. The [roadmap](/prologue/roadmap) lists what is planned.

## Features

A content site built with Laravel alone needs a table, a migration and a model for each kind of content. Each one also needs an editing screen, and a second language needs more tables. Lynx builds all of this from a description of your content.

- **Content types without migrations.** You describe a *template* (say, `post`) and its *fields* (`title`, `summary` and `body`) in the admin, in PHP or in YAML. Lynx creates the tables and an editing screen for it.
- **Several languages.** Adding Portuguese to a running site takes one step, with no migration and no deploy. A translatable field falls back to another language when a translation is missing. Each page gets a translated URL such as `/pt/blogue/ola-mundo`.
- **Pages in a tree.** Each page has a parent and can have children. The tree in the admin has the same structure as the site, so editors find a page where they would find it on the site: `/about/team` is the `team` page under `about`. URLs, menus, breadcrumbs and permissions follow the same tree.
- **Actions for every change.** Saving, moving and translating pages are *actions*, and so is adding a field. The admin, your code, Artisan commands, AI tools and the built-in AI agent all run the same actions. Validation, permissions and the activity log apply to each of them.

## Features for experienced editors

Editors who run a site every day need to change many pages at once and to recover from changes that went wrong.

- **Old URLs keep working.** When an editor renames or moves a page, its old URLs redirect to the new address in every language. ([Publishing](/basics/publishing))
- **Content survives template changes.** Switching a page to another template keeps the values of fields the new template lacks. Switching back brings them back. ([Templates and Fields](/basics/templates-and-fields))
- **Drafts and revisions.** An editor prepares changes in a draft, previews it at the page's own URL and publishes it now or on a schedule. Every save becomes a revision that can be compared and restored. ([Publishing](/basics/publishing))
- **Searches across the whole site.** The [Page Finder](/modules/finder) finds pages by what they hold, such as courses tagged AI that cost less than 50. Editors can save a search, change every page it finds in one step and export the results as CSV.
- **Permissions by section.** A role can be limited to a branch of the tree (a page and the pages below it). Each team then edits only its own section of the site. ([Permissions](/admin/permissions))
- **A history for every page.** Each page lists who changed it, when and in which language. Changes made by code, scheduled publishing and AI tools appear there too.
- **Regional languages.** Brazilian Portuguese can fall back to European Portuguese, then to English, so editors translate only the text that differs. Each editor can also choose the language of the admin itself. ([Languages](/basics/languages))

## Features for senior developers

Developers who maintain a site for years need the schema in version control, one place for business rules and queries they can inspect.

- **Schema in Git.** `lynx:schema:export` writes languages, fields, templates and roles as YAML files you commit. `lynx:schema:apply` applies them on every environment through the same actions as the admin. Running it again changes nothing, and it deletes nothing unless you pass `--force`. ([Schema and Content](/deeper/schema-and-content))
- **One write path.** Every change is an action that validates its input, checks permissions and runs its hooks inside one transaction. Events are dispatched after the commit. ([Actions and Hooks](/deeper/actions-and-hooks))
- **Dry runs everywhere.** Any action can run as a dry run, which does all its work, rolls it back and returns what would have changed. Events are never dispatched for a dry run, so no mail is sent and no cache is cleared.
- **Inspectable queries.** A selector compiles to SQL, and `php artisan lynx:find "template=course, sort=title" --sql` prints the query. ([Selectors](/basics/selectors))
- **No implicit data loss.** Removing a field keeps its values as orphans. Only `pages.purge_orphans` deletes them, and it can run as a dry run first.
- **Fallback at read time.** A missing translation is read from the fallback language when the page renders. Values are never copied between languages, and `isFallback()` tells a template that a value came from another language.
- **Plain Laravel.** Pages render with Blade. Admin screens are Livewire components, and a custom field input is a Blade view, with no JavaScript build of your own. Your routes, users, queues and tests stay as they are.
- **AI tools from the same actions.** The MCP server and the built-in agent are generated from the actions. A new action is available to AI tools with the same validation and permissions, and the agent changes nothing until someone approves its plan. ([AI](/ai/))
- **A strict codebase.** Every package passes PHPStan at its highest level with no baseline, and the tests run on SQLite and PostgreSQL. Classes are marked `@api` or `@internal`, so you know which ones are safe to build on.

## Lynx in a Laravel app

Lynx is a set of Composer packages inside a Laravel app. The app can be a new one made for the site or one you already have. After you install Lynx:

- Your routes still come first. Lynx registers one catch-all route that serves pages from the tree for any URL your app does not handle.
- Pages render with Blade. A page that uses the `post` template renders `resources/views/templates/post.blade.php`, which receives the page as `$page`.
- You can read content anywhere in your code. In a controller, a command or a queued job, `lynx()->pages->find('template=post, limit=10')` returns pages.
- Your app keeps its own database tables, users, queues and deployment. Lynx adds tables whose names start with `lynx_`.

## Admin

The `lynxcms/admin` package gives editors a place to work that needs no technical knowledge. It includes:

- the site as a tree of pages, which editors can open, reorder and move by dragging;
- a page editor that shows the fields in the layout you set, with plain labels and a tab per language;
- drafts that editors can preview before publishing, and scheduled publishing;
- a history of every save, which editors can compare and restore;
- a trash, for the day someone deletes the wrong page.

Developer details, such as template names and field types, are shown only to superusers (the accounts allowed to do everything). A site with one language never mentions translations.

### Running without the admin

The admin is optional. With only `lynxcms/core`, you define templates and fields in PHP or in YAML files. Content comes from code, Tinker (`php artisan tinker`), seeders (Laravel classes that fill the database), imports or [content packages](/deeper/schema-and-content). AI tools such as Claude can work with the site through the [MCP server](/ai/).

Your app can also build its own editing screens on the same API. Permissions, validation and the other rules still apply, because they live in the [actions](/getting-started/concepts#actions).

## Livewire

[Livewire](https://livewire.laravel.com) is a Laravel library for interactive parts of a page, written in PHP instead of JavaScript. A form that checks its fields as you type is one example. Pages render with Blade, so a template can use any Livewire component of your app, such as a newsletter form or a search filter. A [`component` field](/basics/field-types#component) lets editors place components on pages and fill in their settings. [Views](/basics/views#livewire-components) covers both.

The admin is also built with Livewire 4. You can add your own screens to it the same way you build the rest of your app, as [Extending the Admin](/admin/extending) describes.

## Packages

Only `lynxcms/core` is required. Add the others when a site needs them. Each one has its own page in this guide.

| Package | Contents |
| --- | --- |
| `lynxcms/core` | Languages, fields, templates, pages, selectors, actions, routing and Blade helpers. |
| `lynxcms/admin` | The admin at `/lynx`: page tree, page editor, fields, templates, languages, roles and users. |
| `lynxcms/menus` | Menus that editors build, and the `<x-lynx-menu>` component. |
| `lynxcms/media` | Image and file fields, with resizing and focus points. |
| `lynxcms/finder` | A screen in the admin that finds pages by their fields, saves searches, changes the pages found and exports them. |
| `lynxcms/forms` | Forms defined in PHP or built in the admin, with an inbox for submissions. |
| `lynxcms/seo` | Titles, descriptions, social cards and structured data for each page. |
| `lynxcms/sitemap` | An XML sitemap with every language. |
| `lynxcms/cache` | A page cache for guests. Cached pages are sent without rendering, or without PHP at all when web server rules serve them. |
| `lynxcms/page-password` | Pages that ask visitors for a password. |
| `lynxcms/static-site` | An export of the site as static files. |
| `lynxcms/mcp` | An MCP server that lets AI tools such as Claude work with the site. |
| `lynxcms/ai` | A built-in AI agent and machine translation. |
| `lynxcms/contracts` | Interfaces the packages share. It is installed with the other packages. |

## Next steps

- [Installation](/getting-started/installation) adds Lynx to a new or existing Laravel app.
- [Your First Site](/getting-started/first-site) builds a small blog in two languages, step by step.
- [Core Concepts](/getting-started/concepts) explains pages, templates, fields, languages and actions in more depth.
