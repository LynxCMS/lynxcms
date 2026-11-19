# Lynx CMS

**A multilingual CMS for Laravel that people and AI agents can build and run safely.**

> [!WARNING]
> **Lynx is not 1.0 yet.** Releases are tagged `0.1.x` on Packagist, and any of them may change APIs, database tables or configuration. Each release lists what changed in the [release notes](docs/prologue/release-notes.md) and what to do in the [upgrade guide](docs/prologue/upgrade.md). Try it, build with it and open issues, but pin an exact version and read both before you update a site you depend on.

Lynx brings [ProcessWire](https://processwire.com)'s page tree, runtime fields and selector API to Laravel 13, as a standalone site in a new app or added to one you already have, with languages as a core concept and every change going through validated actions that people, Artisan, MCP clients and AI agents share.

## Why Lynx

- **Languages are rows, not columns.** Add a language to a running site with one insert. No migration, no deploy.
- **Fallback at read time, field by field.** A partly translated page shows each field in its own language or in the next one of its fallback chain (`pt-BR`, then `pt`, then the default), and templates know which is which.
- **Translated URLs with history.** `/courses/delivery-8` and `/pt/cursos/navegando-entrega-8`. Renamed or moved pages keep redirecting from their old addresses.
- **Fields and templates at runtime.** Text, rich text, Markdown, numbers, dates, choices, page references, repeaters, typed blocks (matrix), images and files, added from PHP, the admin or YAML schema files.
- **Selectors.** `template=course, price<300, title%=Delivery, sort=-title, limit=10` finds pages by the values visitors see, in the current language.
- **Every write is an action.** The PHP API, Artisan, the admin, the MCP server and the built in agent call the same actions, with validation, hooks, permissions, dry runs and an activity log.
- **AI native.** An MCP server and a `laravel/ai` agent are generated from the action registry. The agent plans first and applies only what you approve.
- **Closed by default.** A user without a role can do nothing. Roles grant permissions per template and per branch, and the admin signs people in with two-factor authentication and passkeys.
- **An admin in three languages.** English, Portuguese and Spanish, chosen by each editor, apart from the languages of the site.
- **Fast by default.** Stored menus, fields loaded on first read, a page cache that lets nginx or Apache send pages without PHP, and a static site export.

## A quick look

```php
$lynx = lynx();

// Languages are added at runtime
$lynx->languages->update('default', ['name' => 'English', 'locale' => 'en']);
$lynx->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'default');

// Fields and templates
$lynx->fields->add('body', 'textarea', translatable: true, settings: ['format' => 'html']);
$lynx->fields->add('price', 'integer');
$lynx->templates->add('course', fields: [['name' => 'title', 'required' => true], 'body', 'price']);

// Pages
$page = $lynx->pages->new('course', '/courses/');
$page->name = 'delivery-8';
$page->title = 'Navigating Project Delivery';
$page->body = '<p>Only in English for now.</p>';
$page->price = 249;
$page->setLanguageName('pt', 'navegando-entrega-8');
$page->setLanguageValue('pt', 'title', 'Navegando pela Entrega de Projetos');
$page->save();

// Reading, with fallback per field
$lynx->language('pt', function () use ($page) {
    echo $page->title;               // Navegando pela Entrega de Projetos
    echo $page->body;                // the English body, as a fallback
    echo $page->body->isFallback();  // true
    echo $page->url;                 // /pt/cursos/navegando-entrega-8
});

// Selectors
$lynx->pages->find('template=course, price<300, sort=-title, limit=10');

// Every write can be previewed
$lynx->pages->delete('/courses/', dryRun: true)->changes->summary();
```

A page on template `course` renders `resources/views/templates/course.blade.php`:

```blade
<html lang="{{ lynx()->languages->current()->appLocale() }}">
<head>@hreflang($page)</head>
<body>
    <x-lynx-menu name="top" />
    <h1>@field($page->title)</h1>        {{-- marks a fallback with its lang --}}
    @field($page->body)
    <p>{{ $page->category?->title }}</p>  {{-- a single page reference, or null --}}
</body>
</html>
```

## Packages

This repository is a monorepo: every package lives in `packages/`, and changes are made here. Each package is also copied to its own read-only repository ([LynxCMS/core](https://github.com/LynxCMS/core), [LynxCMS/admin](https://github.com/LynxCMS/admin)...) on every push, ready for Packagist.

| Package | What it adds |
| --- | --- |
| `lynxcms/core` | Languages, fields, templates, pages, selectors, site search, actions, routing and Blade helpers. |
| `lynxcms/contracts` | Interfaces and data objects that modules depend on. |
| `lynxcms/admin` | The Livewire admin: page tree, editor, drafts and revisions, trash, schema screens, languages, roles, users, activity, and sign in with two-factor authentication and passkeys. |
| `lynxcms/menus` | Multilingual menus stored as pages, the menu editor and `<x-lynx-menu>`. |
| `lynxcms/media` | Images and files on pages, with sizes made on demand, private files and expiring links. |
| `lynxcms/forms` | Forms defined in code or built in the admin, rendered with Livewire, with submissions, results and exports in the admin. |
| `lynxcms/finder` | Find pages in the admin by their fields, template, status and dates, save searches, change the pages found together and export them. |
| `lynxcms/seo` | Meta and social tags, canonical links, JSON-LD and `robots.txt`. |
| `lynxcms/sitemap` | XML sitemaps for every language. |
| `lynxcms/page-password` | Password protected pages and branches. |
| `lynxcms/cache` | Page cache, islands and fragment cache. |
| `lynxcms/static-site` | Exports the whole site as static files, to host without PHP. |
| `lynxcms/mcp` | An MCP server generated from the actions. |
| `lynxcms/ai` | The built in agent and the translation provider, on the Laravel AI SDK. |

Machine translation in the admin (`lynxcms/translate`) is a paid module on the [roadmap](docs/prologue/roadmap.md).

## Trying it

Requirements: PHP 8.5, Laravel 13, and SQLite or PostgreSQL. MySQL is not supported; [Choosing a database](docs/getting-started/database.md) says which to use and why.

### The demo in this repository

The workbench holds a small multilingual course site with the admin and demo users.

```bash
git clone https://github.com/LynxCMS/lynxcms.git && cd lynxcms
composer install
npm --prefix packages/admin ci && composer assets   # the admin's CSS and JS, built locally
composer build      # creates the demo data (and wipes it when run again)
composer serve      # the site, and the admin at /lynx/pages
```

Opening the admin signs you in as the demo superuser. `/demo/sign-in/editor` and `/demo/sign-in/courses` show what other roles see, and every demo password is `password`.

### A starter site

Each starter is a finished Laravel app built with Lynx, ready to clone and change. [Guides and Starters](docs/guides/index.md) shows how to install each one.

| Starter | What it is |
| --- | --- |
| [lynx-corporate-starter](https://github.com/LynxCMS/lynx-corporate-starter) | A software company's website in English and Portuguese ([live demo](https://demo-corporate.lynxcms.org/)). |
| [starter-training](https://github.com/LynxCMS/starter-training) | A bilingual training company: leads, paid bookings, members and surveys ([guide](docs/guides/training-site/index.md)). |
| [starter-meeting-rooms](https://github.com/LynxCMS/starter-meeting-rooms) | A law firm's website and meeting rooms on door screens ([guide](docs/guides/meeting-rooms/index.md), [live demo](https://meeting-rooms.lynxcms.org/)). |

### In your own Laravel app

```bash
composer require lynxcms/core lynxcms/admin lynxcms/media lynxcms/menus
php artisan migrate
php artisan lynx:install            # asks for your superuser's name, email and password
```

Add the other packages you need from the table above. The [installation guide](docs/getting-started/installation.md) covers the first superuser, the scheduler, shared hosting and developing against a local clone of this repository.

## Documentation

The documentation is at **[lynxcms.org/cms/docs](https://lynxcms.org/cms/docs/)**. Its Markdown lives in [`docs/`](docs/); the site is built with VitePress and served by Cloudflare, deployed from `main`.

- [Getting started](docs/getting-started/introduction.md): introduction, installation, [your first site](docs/getting-started/first-site.md) and the core concepts.
- [The basics](docs/basics/): templates and fields, views, selectors, languages, images and files, publishing.
- [Guides](docs/guides/index.md): a bilingual website, a training company site and a meeting rooms app, built step by step.
- [The admin](docs/admin/index.md), [modules](docs/modules/) (menus, forms, Page Finder, SEO, sitemap, page cache, page passwords, static export) and [AI](docs/ai/index.md).
- [Digging deeper](docs/deeper/): actions and hooks, visibility rules, schema and content packages, custom field types, server capacity.
- [Reference](docs/reference/): Artisan commands and configuration.
- [Release notes](docs/prologue/release-notes.md), the [upgrade guide](docs/prologue/upgrade.md) and the [roadmap](docs/prologue/roadmap.md).

To work on the documentation, run `npm --prefix docs ci` once, then `npm --prefix docs run dev` for a live preview. `npm --prefix docs run build` checks every link, as CI does.

The design decisions are recorded as ADRs in [`documentation/ADRs.md`](documentation/ADRs.md), and [`AGENTS.md`](AGENTS.md) describes the architecture and the rules for contributors, people and coding agents alike.

## Development

```bash
composer test                          # SQLite, in parallel
composer test:pgsql                    # PostgreSQL, a database per test process
composer test:all                      # SQLite, PostgreSQL and a table prefix, as CI runs them
composer check                         # Pint, Rector and PHPStan at level max
composer assets                        # build the admin's CSS and JS (run npm --prefix packages/admin ci once first)
```

Every change comes with tests, and every action is tested for its happy path, a validation failure, a rollback and a dry run. Read `AGENTS.md` before opening a pull request.

## Status

Lynx is in active development, released as `0.1.x`. Expect breaking changes until 1.0; each one is in the release notes with its upgrade steps. Only the latest release gets security fixes, and [SECURITY.md](SECURITY.md) says how to report a vulnerability privately. Feedback and issues are welcome, and larger pull requests need a discussion first while the design settles.

## License

Lynx is open source under the [MIT license](LICENSE).
