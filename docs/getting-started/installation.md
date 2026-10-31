---
title: Installation
description: Add Lynx to a Laravel app, create the first admin user and add modules later.
---

# Installation

This page installs Lynx and its admin, and creates the first account that can sign in. It takes a few minutes. The end of the page covers modules, configuration and hosting, for when you need them.

## Requirements

Lynx is a set of Laravel packages, so it needs what Laravel 13 needs, plus:

- PHP 8.5 or newer.
- PostgreSQL (recommended) or SQLite. MySQL and MariaDB are not supported.
- Laravel's scheduler, which needs one cron entry (cron is the server's timer for recurring commands), if editors schedule pages or you want automatic backups.

Nothing else runs on the server: the admin's CSS and JavaScript come prebuilt, so production servers need no Node.js.

## Creating a Laravel app

Lynx can run a whole site on its own or join an app you already have. Either way it lives in a Laravel app: for a standalone site, create a new one and Lynx serves every page, with no routes or controllers of your own. To add Lynx to an existing app, skip to [Installing Lynx](#installing-lynx).

To create a new app, use the Laravel installer or Composer, as the [Laravel documentation](https://laravel.com/docs/installation) describes:

```bash
laravel new blog
cd blog
```

Pick SQLite or PostgreSQL when the installer asks for a database. You can also set it later in `.env`.

## Installing Lynx

Require the core and the admin with Composer, then run the migrations, which create Lynx's database tables:

```bash
composer require lynxcms/core lynxcms/admin
php artisan migrate
```

Lynx serves pages from a catch-all route that comes after your own routes. A new Laravel app answers `/` with its welcome page, so remove that route from `routes/web.php` to let Lynx serve the home page:

```php
// routes/web.php

// Remove this, or move it to another URL:
Route::get('/', function () {
    return view('welcome');
});
```

### Running the installer

```bash
php artisan lynx:install
```

`lynx:install` creates the default language, the home page, a `simple-page` template with a title for your first pages, and three roles, the sets of permissions people get: `superuser` (allowed everything), `editor` (works on pages) and `guest` (allowed nothing). When no one is a superuser yet, it offers to create one: give a name, an email and a password, and the account is created in your app's `users` table with the `superuser` role. Running it again changes nothing, so it is safe in deployment scripts. It asks only when you type it in a terminal; run from code (`Artisan::call('lynx:install')` in a seeder or a test) or with `--no-interaction`, it only warns.

If your app already has accounts, make one of them the superuser instead:

```bash
php artisan lynx:install --user=ada@example.com     # an email, or a user id
```

> [!IMPORTANT]
> A user without a role can do nothing in Lynx, so until someone is a superuser, nobody can open the admin. `php artisan lynx:superuser` creates a superuser, or makes an existing account one, at any time, also on a site that lost its last superuser. People who sign up on the site get the `guest` role, which grants nothing (see [People who sign up](/admin/permissions#people-who-sign-up)).

In a deploy script or CI, give everything as options: `php artisan lynx:superuser --email=ada@example.com --name=Ada --password=... --no-interaction`. A password on the command line stays in the shell's history, so prefer the question where you can.

## Signing in

Start the app with `php artisan serve` (or your usual local server, such as Herd or Sail) and open `/lynx`. If your app does not set up [Laravel Fortify](https://laravel.com/docs/fortify) itself, Lynx shows its own sign-in screen at `/lynx/login`. After signing in you see the page tree, which holds only the home page for now.

The admin also offers password resets, two-factor authentication and passkeys. Some of them need a few lines in your `User` model; see [Sign-in and Accounts](/admin/sign-in).

## Next steps

[Your First Site](/getting-started/first-site) continues from here and builds a small blog in two languages.

## Adding modules

Modules are Composer packages, listed in the [introduction](/getting-started/introduction#the-packages). Install one with Composer. When it brings database changes, Composer's run of `package:discover` ends with a warning that names the module:

```
WARN  lynxcms/cache has 2 database changes waiting. Run php artisan lynx:migrate once the database is backed up.
```

Back up the database, then apply them:

```bash
composer require lynxcms/cache
php artisan lynx:migrate
```

`lynx:migrate` lists the changes by package and asks whether the database is backed up before it runs them. `--force` skips the question, for deployments. It runs only the migrations of Lynx packages; `php artisan migrate` works too and runs your app's as well.

The media module stores uploads on Laravel's `public` disk, so after installing it, make that disk reachable from the web once:

```bash
composer require lynxcms/media
php artisan storage:link
```

Until the changes are applied, the module's screens fail with a missing table error, and for the page cache the page editor does too. If your app caches its configuration and routes (`php artisan optimize`, as most deployments do), run it again so the module's screens are found.

## Configuration

Lynx works without configuration. Its settings read environment variables, so most changes are a line in `.env`, such as `LYNX_TRAILING_SLASH=true` to end page URLs with a slash. To change a setting that has no variable, publish the configuration file:

```bash
php artisan vendor:publish --tag=lynx-config
```

[Configuration](/reference/configuration) lists every setting.

## The scheduler

Scheduled publishing and optional backups run through Laravel's scheduler. Add the usual cron entry on the server:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Without it, pages scheduled to publish or unpublish never change.

## The queue worker

Lynx sends form emails through Laravel's queue, and a new Laravel app keeps queued jobs in the database until a worker runs them. Without a worker, visitors still read their thank-you message and the submission reaches the admin, but the email never goes out. Keep one running on the server, as [Laravel's queue guide](https://laravel.com/docs/queues#supervisor-configuration) describes:

```bash
php artisan queue:work
```

To send emails during the request instead, with no worker, set `QUEUE_CONNECTION=sync` in `.env`. A visitor then waits while the email goes out.

## Shared hosting

Lynx runs on low cost shared and managed hosting (DreamHost, for example) as long as the plan offers PHP 8.5, SSH and cron. Without root access some things work differently:

- **Database.** These plans usually offer MySQL only, which Lynx does not support. Use SQLite: it needs no server, and it renders pages faster than a PostgreSQL server on the same machine. Its limit is many writes at the same time, which a site with a few editors never reaches. Keep the database file outside `public/`. If the host keeps sites on local disks, turn on write ahead logging in the `sqlite` connection of `config/database.php` (`'journal_mode' => 'wal'`, `'busy_timeout' => 5000`); on network storage, leave the defaults.
- **Web folder.** Point the domain's web folder at the app's `public/` folder in the hosting panel. If the panel can't, put a `.htaccess` in the app's root that forwards everything to `public/` (`RewriteEngine On` and `RewriteRule ^(.*)$ public/$1 [L]`), and keep `.env` and the rest of the app unreadable from the web.
- **Page cache.** Most shared hosts run Apache with `mod_rewrite` and `mod_headers`. Print the rules with `php artisan lynx:page-cache:server apache` and add them to `public/.htaccess`: stored pages are then sent without PHP, with either web folder above. If the host doesn't allow them, use the `serve.php` line instead ([Page Cache](/modules/page-cache#sending-pages-without-php)).
- **PHP version on the command line.** The `php` command over SSH and in cron may be an older version than the site uses. Call PHP 8.5 by its full path (the host's documentation gives it), in the cron entry too.
- **Composer.** Shared servers stop processes that use too much memory, and `composer install` can be one of them. Install locally or in CI with `composer install --no-dev --optimize-autoloader` and upload the `vendor` folder.
- **Queues.** A shared plan can't keep a queue worker running. Set `QUEUE_CONNECTION=sync` so form notifications are sent during the request, or let cron run `php artisan queue:work --stop-when-empty` every minute.
- **Extensions.** Check with `php -m` that `pdo_sqlite` and `gd` (or `imagick`, for image fields) are there, besides those Laravel needs.

A shared server divides its processor among many customers, so the numbers in [Server Capacity](/deeper/server-capacity) don't apply. With the page cache on, most visitors never reach PHP, which is what makes cheap hosting work. Once the site needs PostgreSQL, a queue worker or steady performance, move to a VPS (a virtual server of your own) with root access.

## Developing against a local checkout

To work on Lynx itself while building a site, point Composer at a local clone of the [Lynx monorepo](https://github.com/LynxCMS/lynxcms) with a path repository. In the site's `composer.json`:

```json
{
    "minimum-stability": "dev",
    "prefer-stable": true,
    "repositories": [
        {
            "type": "path",
            "url": "../lynxcms/packages/*",
            "options": {
                "symlink": true,
                "versions": {
                    "lynxcms/contracts": "dev-main",
                    "lynxcms/core": "dev-main",
                    "lynxcms/admin": "dev-main",
                    "lynxcms/menus": "dev-main",
                    "lynxcms/mcp": "dev-main",
                    "lynxcms/ai": "dev-main"
                }
            }
        }
    ]
}
```

Then run `composer require lynxcms/core:@dev lynxcms/admin:@dev lynxcms/menus:@dev`. The packages require each other at the same version, so every one of them needs a version in `options.versions`. With `symlink`, changes in the checkout show up in the site at once.

The admin's CSS and JavaScript are not committed in the monorepo, so build them once in the checkout, and again after pulling changes to the admin's views: `npm --prefix packages/admin ci && composer assets`.
