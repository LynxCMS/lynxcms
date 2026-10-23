---
title: Lynx Compared
description: How Lynx's features compare with Statamic, ProcessWire, Laravel Folio and WordPress, and when another tool fits better.
---

# Lynx Compared

If you are choosing a way to build a content site with Laravel, you are probably also looking at Statamic, ProcessWire, Laravel Folio or WordPress. This page compares their **features**, so you can see where Lynx is different, where it is the same, and where another tool fits your project better.

> [!NOTE]
> Every product here changes over time. These facts were checked against each project's documentation and announcements on **23 October 2026**; the sources are listed at the end of the page. If something has changed, please [open an issue](https://github.com/LynxCMS/lynxcms/issues).

## At a glance

| | Lynx | Statamic | ProcessWire | Laravel Folio | WordPress |
| --- | --- | --- | --- | --- | --- |
| **What it is** | A CMS in Laravel packages | A CMS in a Laravel package | A CMS and PHP framework | A page router for Laravel | A CMS and PHP application |
| **Lives inside a Laravel app** | Yes | Yes | No | Yes | No |
| **Where content is stored** | PostgreSQL or SQLite | Markdown and YAML files, or a database with its Eloquent driver | MySQL or MariaDB (SQLite and PostgreSQL are experimental on its development branch) | Nowhere: your Blade files and your own models | MySQL or MariaDB |
| **Content types** | Templates and fields, made at runtime in the admin, PHP or YAML; tables are created for you | Collections, blueprints and fieldsets, in the control panel or YAML files | Templates and fields, made at runtime in the admin or the API | None | Posts, pages and custom post types; custom fields come from plugins or code |
| **Page tree** | Every page in one tree | Trees for structured collections | Every page in one tree | Folders of Blade files | Hierarchical pages; posts are not in a tree |
| **Finding content** | Selectors that compile to SQL | Query builder and Antlers tags | Selectors | Your own Eloquent queries | `WP_Query` |
| **Templates** | Blade | Antlers or Blade | PHP files | Blade | PHP themes or block themes |
| **Multiple languages** | In the core: per-field fallback chains, translated URLs | Multi-site: a site per language, translations inherit from their origin entry | Core modules: translated fields and URLs, fallback to the default language | None built in | Not in the core; plugins add it |
| **Admin** | Optional, built with Livewire 4 | Control panel built with Vue 3 and Inertia | Built in | None | Built in, with the block editor |
| **Livewire on pages** | Yes, and editors can place components | Yes, in Blade views | No | Yes | No |
| **Drafts, revisions, scheduling** | Built in | Revisions and date based publishing | Unpublished pages; drafts and revisions through modules | None | Built in |
| **Content API** | PHP and MCP; no REST or GraphQL API yet | REST and GraphQL | PHP; JSON through modules or your own templates | Your own routes | REST API |
| **AI tools** | MCP server and an AI agent, generated from its actions | Laravel Boost guidelines | Agent Tools module (2026) | None of its own | Abilities API in the core; MCP through the official MCP Adapter plugin |
| **Maturity** | Before 1.0: APIs may change | Mature, with an add-on marketplace | Mature, with a modules directory | Official Laravel package | The largest ecosystem of themes and plugins |

## Lynx and Statamic

Statamic is the closest to Lynx: both are Laravel packages that add a content model, an admin and Blade templates to your app.

**What differs:**

- **Storage.** Statamic stores content in Markdown and YAML files by default, which suits sites managed in Git, and can move it to a database with its Eloquent driver. Lynx always stores content in the database, PostgreSQL or SQLite, and keeps the *schema* in YAML files you can commit.
- **Languages.** In Statamic, each language is a site, and a translated entry inherits what it doesn't set from its origin entry. In Lynx, languages are rows: each translatable field falls back along a chain you choose (Brazilian Portuguese to Portuguese to English), field by field, and page URLs are translated per language.
- **Structure.** Statamic organises content in collections, some of which can be ordered as trees. In Lynx, every page lives in one tree, so URLs, menus and permissions follow the structure editors see.
- **The admin.** Statamic's control panel is built with Vue 3 and Inertia. Lynx's admin is built with Livewire 4, and it is optional.
- **Actions.** Every change in Lynx is an action with permissions, hooks, dry runs and an activity log, and the MCP server and AI agent are generated from the same actions.

**Statamic fits better when** you want content in flat files under version control, a REST or GraphQL API, a mature product with a large add-on marketplace, or Antlers templates.

## Lynx and ProcessWire

Lynx takes several ideas from ProcessWire: one tree of pages, templates and fields created at runtime, and selectors for finding pages. If you know ProcessWire, Lynx will feel familiar.

**What differs:**

- **Laravel.** ProcessWire is its own PHP framework. Lynx runs inside a Laravel app, so you keep Laravel's routing, Blade, Eloquent, queues, Livewire and the rest of its ecosystem.
- **Languages.** ProcessWire's multi-language support comes from core modules, and a blank translation falls back to the default language; other fallback orders need a hook. Lynx falls back along a chain per language, and marks fallback text with its language in the HTML.
- **Databases.** ProcessWire runs on MySQL or MariaDB, with SQLite and PostgreSQL support experimental on its development branch. Lynx runs on PostgreSQL or SQLite, and does not support MySQL.
- **Every write is an action**, shared by the admin, code, Artisan, the MCP server and the AI agent.

**ProcessWire fits better when** you don't want a Laravel app, you host on MySQL, or you want a mature CMS with a long record and a large directory of modules.

## Lynx and Laravel Folio

Folio is not a CMS: it is a router that turns Blade files in `resources/views/pages` into routes, with parameters, route model binding, middleware and named routes. You can use Folio and Lynx in the same app, since Lynx only serves the URLs your app's own routes leave free.

**What differs:**

- With Folio, a page is a Blade file a developer writes. With Lynx, a page is content that editors create and change in the admin, with fields, languages, drafts and history.
- Folio has no content storage, admin or language support. You build those yourself, or add a CMS.

**Folio fits better when** your pages are written by developers, change with deployments, and don't need an editing interface, such as a product's marketing pages or an app's static screens.

## Lynx and WordPress

WordPress powers a large part of the web, and its ecosystem of themes and plugins is the largest of any CMS.

**What differs:**

- **Laravel.** WordPress is its own PHP application, with its own conventions. Lynx is part of your Laravel app, so content and the rest of your application share models, users, queues and code.
- **Content model.** WordPress has posts and pages, and custom post types and fields come from plugins or code. Lynx's templates and fields are its core: any page can have any fields, without plugins.
- **Languages.** WordPress has no multilingual support in its core; plugins add it. In Lynx, languages, fallback and translated URLs are part of the core.
- **AI.** WordPress's core has an Abilities API, and the official MCP Adapter plugin exposes abilities to AI tools. Lynx generates its MCP server and AI agent from its actions, so every change an agent can make goes through the same validation and permissions as the admin.

**WordPress fits better when** the site needs plugins that only exist for WordPress, the team already works in WordPress, or the site must run on hosting that only offers WordPress.

## Sources

Checked on 23 October 2026:

- Statamic: [Statamic 6 release](https://statamic.com/blog/statamic-6), [multi-site](https://statamic.dev/control-panel/multi-site), [localizing entries](https://statamic.dev/tips/localizing-entries), [tips on the Eloquent driver](https://statamic.dev/knowledge-base/tips).
- ProcessWire: [blog and releases](https://processwire.com/blog/), [multi-language support](https://processwire.com/docs/multi-language-support/), [multi-language fields](https://processwire.com/docs/multi-language-support/multi-language-fields/).
- Laravel Folio: [documentation](https://laravel.com/docs/13.x/folio).
- WordPress: [multilingual WordPress](https://developer.wordpress.org/advanced-administration/wordpress/multilingual/), [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/), [mcp-adapter releases](https://github.com/WordPress/mcp-adapter/releases).
