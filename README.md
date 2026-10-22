# Lynx CMS

**A multilingual CMS for Laravel that people and AI agents can build and run safely.**

> [!WARNING]
> **Lynx is not ready for production.** It is under active development and has not had a release yet. APIs, database tables, configuration and package names will change, sometimes without a migration path, and the structure of this repository may change too (packages may move into their own repositories). Try it, read it and open issues, but do not build a site you depend on with it yet.

Lynx brings [ProcessWire](https://processwire.com)'s page tree, runtime fields and selector API into any Laravel 13 app, with languages as a core concept and every change going through validated actions that people, Artisan, MCP clients and AI agents share.

## Why Lynx

- **Languages are rows, not columns.** Add a language to a running site with one insert. No migration, no deploy.
- **Fallback at read time, field by field.** A partly translated page shows each field in its own language or in the next one of its fallback chain (`pt-BR`, then `pt`, then the default), and templates know which is which.
- **Translated URLs with history.** `/courses/delivery-8` and `/pt/cursos/navegando-entrega-8`. Renamed or moved pages keep redirecting from their old addresses.
- **Fields and templates at runtime.** Text, rich text, Markdown, numbers, dates, choices, page references, repeaters, typed blocks (matrix), images and files, added from PHP, the admin or YAML schema files.
- **Selectors.** `template=course, price<300, title%=Delivery, sort=-title, limit=10` finds pages by the values visitors see, in the current language.
- **Every write is an action.** The PHP API, Artisan, the admin, the MCP server and the built in agent call the same actions, with validation, hooks, permissions, dry runs and an activity log.
- **AI native.** An MCP server and a `laravel/ai` agent are generated from the action registry. The agent plans first and applies only what you approve.
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
| `lynxcms/core` | Languages, fields, templates, pages, selectors, actions, routing and Blade helpers. |
| `lynxcms/contracts` | Interfaces and data objects that modules depend on. |
| `lynxcms/admin` | The Livewire admin: page tree, editor, drafts and revisions, schema screens, languages, roles, users and sign in. |
| `lynxcms/menus` | Multilingual menus stored as pages, the menu editor and `<x-lynx-menu>`. |
| `lynxcms/media` | Images and files on pages, with sizes made on demand. |
| `lynxcms/forms` | Forms defined in code, rendered with Livewire, with submissions in the admin. |
| `lynxcms/seo` | Meta and social tags, canonical links, JSON-LD and `robots.txt`. |
| `lynxcms/sitemap` | XML sitemaps for every language. |
| `lynxcms/page-password` | Password protected pages and branches. |
| `lynxcms/cache` | Page cache, islands and fragment cache. |
| `lynxcms/static-site` | Exports the whole site as static files, to host without PHP. |
| `lynxcms/mcp` | An MCP server generated from the actions. |
| `lynxcms/ai` | The built in agent and the translation provider, on the Laravel AI SDK. |

## Trying it

Requirements: PHP 8.5, Laravel 13, and PostgreSQL (recommended) or SQLite.

### The demo in this repository

The workbench holds a small multilingual course site with the admin and demo users.

```bash
git clone https://github.com/LynxCMS/lynxcms.git && cd lynxcms
composer install
composer build      # creates the demo data (and wipes it when run again)
composer serve      # the site, and the admin at /lynx/pages
```

Opening the admin signs you in as the demo superuser. `/demo/sign-in/editor` and `/demo/sign-in/courses` show what other roles see, and every demo password is `password`.

### In your own Laravel app

```bash
composer require lynxcms/core lynxcms/admin lynxcms/media lynxcms/menus
php artisan migrate
php artisan lynx:install --user=1   # your user id becomes a superuser
```

Add the other packages you need from the table above. The [installation guide](docs/getting-started/installation.md) covers the user model, the scheduler, shared hosting and developing against a local clone of this repository.

## Documentation

The documentation is Markdown in [`docs/`](docs/index.md):

- [Getting started](docs/getting-started/_index.md): installation, a quick tour and the core concepts.
- [Content](docs/content/_index.md): languages, fields and templates, selectors, images and files, moving content between sites.
- [Front end](docs/front-end/_index.md): templates, menus, forms, SEO, sitemaps, page passwords, the page cache, server capacity and the static export.
- [Publishing](docs/publishing/_index.md), [Admin](docs/admin/_index.md) and [AI](docs/ai/_index.md).
- [Reference](docs/reference/_index.md): Artisan commands, configuration, actions and hooks, field types.

[LynxCMS/docs-site](https://github.com/LynxCMS/docs-site) serves them as a website.

The design decisions are recorded as ADRs in [`documentation/ADRs.md`](documentation/ADRs.md), and [`AGENTS.md`](AGENTS.md) describes the architecture and the rules for contributors, people and coding agents alike.

## Development

```bash
composer test                          # SQLite, in parallel
composer test:pgsql                    # PostgreSQL, a database per test process
composer test:all                      # SQLite, PostgreSQL and a table prefix, as CI runs them
composer check                         # Pint, Rector and PHPStan at level max
(cd packages/admin && npm run check)   # rebuild the admin assets and check they are committed
```

Every change comes with tests, and every action is tested for its happy path, a validation failure, a rollback and a dry run. Read `AGENTS.md` before opening a pull request.

## Status

Lynx is in active development. Feedback and issues are welcome. Expect breaking changes until 1.0, and expect larger pull requests to need a discussion first while the design settles.

## License

Lynx is open source under the [MIT license](LICENSE).
