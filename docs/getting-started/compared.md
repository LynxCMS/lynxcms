---
title: Lynx Compared
description: How Lynx's features compare with Statamic, ProcessWire, Laravel Folio and WordPress, and when another tool fits better.
---

# Lynx Compared

If you are choosing a way to build a content site with Laravel, you are probably also looking at Statamic, ProcessWire, Laravel Folio or WordPress. This page compares their **features**, so you can see where Lynx is different, where it is the same, and where another tool fits your project better.

A difference only matters if it changes something for someone. So after the table, each difference says what it means **for developers**, who build and maintain the site, and **for clients and editors**, who own it and work in it every day. Where a difference changes nothing for one of them, the page says so.

> [!NOTE]
> Every product here changes over time. These facts were checked against each project's documentation and announcements on **23 October 2026**; the sources are listed at the end of the page. If something has changed, please [open an issue](https://github.com/LynxCMS/lynxcms/issues).

## At a glance

<div class="wide-table">

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

</div>

## Lynx and Statamic

Statamic is the closest to Lynx: both are Laravel packages that add a content model, an admin and Blade templates to your app.

### Where content is stored

Statamic stores content in Markdown and YAML files by default, and can move it to a database with its Eloquent driver. Lynx always stores content in the database, PostgreSQL or SQLite, and keeps only the *schema* (fields and templates) in YAML files you commit.

- **For developers:** with files, content lives in Git next to the code, with diffs and history for free, and needs no database server; content edited on the live site has to find its way back into the repository. With Lynx, content changes are database transactions, several editors can save at once without file conflicts, and selectors compile to indexed SQL that stays fast with tens of thousands of pages. Schema changes still go through Git, like migrations.
- **For clients and editors:** an edit in Lynx is live as soon as it is saved, and backups are ordinary database backups. With files, content history can also live in Git, which suits sites edited by technical people.

### Languages

In Statamic, each language is a site, and a translated entry inherits what it doesn't set from the entry it was translated from. In Lynx, languages are rows: each translatable field falls back along a chain you choose per language, field by field, and every page has a URL in each language.

- **For developers:** adding a language is one step on a running site, with no configuration per collection, and code reads values the same way in every language, with `isFallback()` when a template needs to know.
- **For clients and editors:** regional variants cost little. A Brazilian Portuguese site can fall back to European Portuguese, then to English, so editors only translate what really differs, and visitors never see an empty field. Untranslated text is marked with its language in the page, which helps screen readers and search engines.

### One page tree

Statamic organises content in collections, some of which can be ordered as trees. In Lynx, every page, of every template, lives in one tree.

- **For developers:** URLs, breadcrumbs and menus follow the tree, and permissions can be limited to a branch (`pages.edit` under `/courses`, for example) instead of being set per content type.
- **For clients and editors:** the admin shows the site the way visitors navigate it, so editors find a page where they expect it, and a team can be given exactly its own section of the site.

### The admin: Livewire and Vue

Statamic's control panel is built with Vue 3 and Inertia. Lynx's admin is built with Livewire 4, and it is optional.

- **For developers:** Lynx's admin is extended with the tools of a Laravel app: a custom field input is a Blade view, and a new screen is a Livewire component, with no Vue and no JavaScript build of your own. In Statamic, a custom fieldtype has a Vue component that you register and build with Vite. For a team that already writes Livewire, Lynx's admin is the same stack as the rest of their work; a team fluent in Vue may prefer Statamic's.
- **For clients and editors:** no difference they would notice from the technology itself. What editors notice is what the admin offers, which the other differences on this page cover.

### Every change is an action

Every change in Lynx, from any source, is an action that checks permissions, runs hooks, can run as a dry run, and is written to an activity log. The MCP server and the AI agent are generated from the same actions.

- **For developers:** validation and permissions live in one place. A rule added to an action, or a hook on it, applies to the admin, your code, Artisan, imports and AI tools at once, and any change can be previewed as a dry run before it runs.
- **For clients and editors:** every change, by a person or by an AI agent, shows up in the activity log with who made it. The built-in agent shows its plan as a dry run, and nothing changes until someone approves it.

**Statamic fits better when** you want content in flat files under version control, a REST or GraphQL API, a mature product with a large add-on marketplace, or Antlers templates.

## Lynx and ProcessWire

Lynx takes several ideas from ProcessWire: one tree of pages, templates and fields created at runtime, and selectors for finding pages. If you know ProcessWire, Lynx will feel familiar.

### Laravel or its own framework

ProcessWire is its own PHP framework. Lynx runs inside a Laravel app.

- **For developers:** you keep Laravel's routing, Eloquent, queues, mail, authentication, Livewire, Pest and the packages around them, and the content and the rest of the application share one codebase, one set of users and one deployment.
- **For clients and editors:** a site that grows into an application, with accounts, bookings or a shop, stays one system with one admin, instead of a CMS next to a separate app.

### Languages

ProcessWire's multi-language support comes from core modules, and a blank translation falls back to the default language; other fallback orders need a hook. Lynx falls back along a chain you set per language.

- **For developers:** regional variants and fallback chains are configuration, not custom hooks, and they apply the same way in templates, selectors, menus, the sitemap and the admin.
- **For clients and editors:** a regional site (Brazilian Portuguese, Swiss German) can reuse a related language's text before falling back to the default one, so less has to be translated twice.

### Databases

ProcessWire runs on MySQL or MariaDB, with SQLite and PostgreSQL support experimental on its development branch. Lynx runs on PostgreSQL or SQLite, and does not support MySQL.

- **For developers:** SQLite needs no database server, which suits small sites and local work; PostgreSQL brings full text search and trigram indexes that Lynx uses for large sites.
- **For clients and editors:** hosting that only offers MySQL can't run Lynx today. SQLite works on most low cost hosting (see [shared hosting](/getting-started/installation#shared-hosting)).

### Every change is an action

ProcessWire offers hooks and, since 2026, an Agent Tools module for AI agents. Lynx runs every change, including an AI agent's, through the same actions.

- **For developers:** the MCP server and the agent are generated from the actions, so every action is available to AI tools with the same validation and permissions, with nothing extra to write.
- **For clients and editors:** an AI agent's changes appear in the activity log like anyone else's, and the built-in agent waits for approval before it changes anything.

**ProcessWire fits better when** you don't want a Laravel app, you host on MySQL, or you want a mature CMS with a long record and a large directory of modules.

## Lynx and Laravel Folio

Folio is not a CMS: it is a router that turns Blade files in `resources/views/pages` into routes, with parameters, route model binding, middleware and named routes. You can use Folio and Lynx in the same app, since Lynx only serves the URLs your app's own routes leave free.

### Who writes the pages

With Folio, a page is a Blade file a developer writes. With Lynx, a page is content that editors create and change in the admin.

- **For developers:** Folio needs nothing beyond Blade and is ideal for pages that change with the code. Lynx adds storage, fields, languages, drafts and history, which you would otherwise build yourself.
- **For clients and editors:** with Folio, every text change needs a developer and a deployment. With Lynx, editors change content themselves, preview it as a draft and publish it when it is ready.

### Languages

Folio has no language support. Lynx has languages, fallback and translated URLs in its core.

- **For developers:** no translation tables, localized routes or language switcher to build.
- **For clients and editors:** a new language can be added and translated in the admin, page by page, without a developer.

**Folio fits better when** your pages are written by developers, change with deployments, and don't need an editing interface, such as a product's marketing pages or an app's static screens.

## Lynx and WordPress

WordPress powers a large part of the web, and its ecosystem of themes and plugins is the largest of any CMS.

### Laravel or its own application

WordPress is its own PHP application, with its own conventions. Lynx is part of your Laravel app.

- **For developers:** you build the site with Laravel's tools and conventions, and content and application code share models, users, queues and tests.
- **For clients and editors:** custom features, such as bookings, accounts or integrations, are built into the same site and admin, instead of being added as separate plugins.

### The content model

WordPress has posts and pages; custom post types and fields come from plugins or code. Lynx's templates and fields are its core.

- **For developers:** any kind of page can have any fields without a plugin, and the schema lives in YAML files you commit and apply on every environment.
- **For clients and editors:** each kind of page has its own form in the editor, with only the fields it needs, laid out in tabs and sections.

### Languages

WordPress has no multilingual support in its core; plugins add it. In Lynx, languages, fallback and translated URLs are part of the core.

- **For developers:** there is no separate multilingual plugin that every other plugin has to support. Menus, SEO, the sitemap, forms and the page cache are language aware out of the box.
- **For clients and editors:** translation works the same way everywhere in the admin, with a tab per language, and untranslated pages fall back instead of breaking.

### Plugins and updates

WordPress's strength is its plugin ecosystem. Lynx's features come in a small set of packages released together.

- **For developers:** fewer third party packages to vet, update and keep compatible, at the cost of a much smaller ecosystem.
- **For clients and editors:** fewer moving parts to keep updated over the life of the site, but also far fewer ready-made features to install.

### AI tools

WordPress's core has an Abilities API, and the official MCP Adapter plugin exposes abilities to AI tools. Lynx generates its MCP server and AI agent from its actions.

- **For developers:** every action, including those of Lynx's modules, is available to AI tools automatically, with the same validation and permissions as the admin.
- **For clients and editors:** an AI agent can only do what its user is allowed to do, every change it makes is logged, and the built-in agent waits for approval.

**WordPress fits better when** the site needs plugins that only exist for WordPress, the team already works in WordPress, or the site must run on hosting that only offers WordPress.

## Sources

Checked on 23 October 2026:

- Statamic: [Statamic 6 release](https://statamic.com/blog/statamic-6), [fieldtypes](https://statamic.dev/extending/fieldtypes), [multi-site](https://statamic.dev/control-panel/multi-site), [localizing entries](https://statamic.dev/tips/localizing-entries), [tips on the Eloquent driver](https://statamic.dev/knowledge-base/tips).
- ProcessWire: [blog and releases](https://processwire.com/blog/), [multi-language support](https://processwire.com/docs/multi-language-support/), [multi-language fields](https://processwire.com/docs/multi-language-support/multi-language-fields/).
- Laravel Folio: [documentation](https://laravel.com/docs/13.x/folio).
- WordPress: [multilingual WordPress](https://developer.wordpress.org/advanced-administration/wordpress/multilingual/), [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/), [mcp-adapter releases](https://github.com/WordPress/mcp-adapter/releases).
