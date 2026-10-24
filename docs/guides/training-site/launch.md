---
title: Launch
description: Chapter 10 of the training site guide. The page cache with islands, warming, backups, a deploy script, moving content between environments, and the owner's AI assistant.
---

# Launch

The site works. This chapter makes it fast, makes it safe to change, and hands the owner the tools to run it. Most of it is configuration and a deploy script, because Lynx already does the work.

## The page cache

Most visitors are guests reading the same pages. The page cache stores each page's HTML the first time a guest asks for it, and serves the stored copy after that, without PHP if the web server is set up for it. [Page Cache](/modules/page-cache) has the numbers and the server rules. Turn it on in `.env`:

```bash
LYNX_PAGE_CACHE=true
```

Then say which pages are kept and what a change clears:

<<< @/snippets/training/database/seeders/AtlasCacheSeeder.php#rules{php}

`parents` clears every page above, so a new session date reaches its course page, the catalogue and the home page's course cards in one save. Staff with `page_cache.manage` see and change these rules under **Settings › Page cache**, and the page editor's **Cache** tab says whether a page is stored, and why not.

Signed-in users never get stored pages, so members, staff and their previews always see the live site. Materials pages are personal, so they are never stored.

## Islands keep the live parts live

A stored page is the same for every guest, but some parts are not: the seats left on a session, and the forms, whose CSRF token and honeypot timing must be fresh. Those parts are **islands**. The stored page holds a placeholder, and one request after the page loads fills in every island of the page for that visitor:

```blade
<x-lynx-island view="partials.session.booking" />
<x-lynx-island livewire="lynx-form" :with="['form' => 'enquiry', 'pageId' => $page->id]" />
```

Chapter 7 put the booking form and seats in an island. This chapter moves the enquiry form on course pages and the newsletter form in the footer into islands too. Pages that are not stored, and signed-in users, get islands rendered in place, so nothing changes for them.

## Deploying

A release runs the same steps every time:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force                 # your app's migrations, and Lynx's
php artisan lynx:schema:apply               # if you keep schema files in Git (chapter 2)
php artisan optimize                        # config, routes, views and events
php artisan lynx:page-cache --clear --warm  # new views: drop stored pages, store them again
php artisan queue:restart                   # workers pick up the new code
```

The server also needs a queue worker for the confirmation emails, the CRM and the mailing list (`php artisan queue:work`, kept alive by Supervisor or your host), and Laravel's scheduler in cron for scheduled publishing and backups:

```bash
* * * * * cd /path/to/atlas && php artisan schedule:run >> /dev/null 2>&1
```

## Backups and moving content

```bash
LYNX_BACKUPS_SCHEDULE=daily
```

Lynx then writes a content package every day to `storage/app/lynx/backups`, keeping the newest 14. A content package holds the schema and every page, so it also starts a new environment, such as a staging server or a colleague's machine, from the live content:

```bash
php artisan migrate --force
php artisan lynx:import atlas-2026-11-02.zip --with-schema
php artisan lynx:install --user=owner@atlas-training.example
```

Import first and install after, so the new environment shares the live site's schema ids, and `lynx:schema:apply` keeps them in step afterwards. See [Schema and Content Packages](/deeper/schema-and-content). A content package is not a database backup: the bookings, users and submissions live in your tables, so back up the database too.

## The owner's AI assistant

Every write in Lynx is an action, and every action is a tool for MCP clients. The owner can ask their assistant to "add a Porto session of the sports nutrition course on 12 January with 14 seats", "translate the new course into Portuguese" or "how did the yoga survey go?", and it works through the same validation, permissions and history as the admin.

For a developer on their own machine, Claude Code starts the server itself:

```bash
claude mcp add atlas -- php artisan mcp:start lynx
```

For the owner, serve it over HTTP with `LYNX_MCP_WEB=true` at `/mcp/lynx`, behind Sanctum: each client acts as the user its token belongs to, so a course manager's assistant can only change courses. See [MCP Server and Agent](/ai/).

## What you get

- **The client:** a fast site that stays right after every edit, daily backups, and an assistant that knows their content and their permissions.
- **You:** caching by configuration, live parts by a tag, a deploy script of seven lines, and environments that move with one package.
