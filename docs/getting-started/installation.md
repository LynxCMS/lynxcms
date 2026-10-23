---
title: Installation
description: Requirements, Composer packages and the first superuser.
order: 1
---

# Installation

## Requirements

- PHP 8.5 and Laravel 13.
- PostgreSQL (recommended) or SQLite.

## Install

```bash
composer require lynxcms/core lynxcms/admin lynxcms/menus lynxcms/mcp lynxcms/ai
php artisan migrate
php artisan lynx:install --user=1
```

`lynx:install` creates the default language and the root page, a `superuser` role and an `editor` role. `--user` gives the user with that id the superuser role.

> [!IMPORTANT]
> Until any user has a role, every signed in user may do everything, so an install or upgrade never locks anyone out. Giving the first role switches the permission checks on. Production sites should always have a superuser.

Only `lynxcms/core` is required. Leave out the admin, menus, MCP or AI packages if you do not need them.

## Adding modules later

Install a module with Composer. When it brings database changes, Composer's run of `package:discover` ends with a warning that names the module:

```
WARN  lynxcms/cache has 2 database changes waiting. Run php artisan lynx:migrate once the database is backed up.
```

Back up the database, then apply them:

```bash
composer require lynxcms/cache
php artisan lynx:migrate
```

`lynx:migrate` lists the changes by package and asks whether the database is backed up before it runs them (`--force` skips the question, for deployments). It runs only Lynx's migrations; `php artisan migrate` works too and runs the app's as well.

Until the changes are applied, the module's screens, and for the page cache the page editor too, fail with a missing table error. If the app caches its configuration and routes (`php artisan optimize`, as most deployments do), run it again so the new screens are found.

## Open the admin

The admin lives at `/lynx/pages`. If your app does not configure Laravel Fortify itself, Lynx signs people in at `/lynx/login`. See [Sign-in and accounts](/docs/admin/sign-in).

## Scheduler

Scheduled publishing and optional backups run through Laravel's scheduler. Add the usual cron entry:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Without it, nothing scheduled is applied.

## Shared hosting

Lynx runs on low cost shared and managed hosting (DreamHost, for example) as long as the plan offers PHP 8.5, SSH and cron. Without root access some things work differently:

- **Database.** These plans usually offer MySQL only, which Lynx does not support. Use SQLite: it needs no server, and it renders pages faster than a PostgreSQL server on the same machine. Its limit is many writes at the same time, which a site with a few editors never reaches. Keep the database file outside `public/`. If the host keeps sites on local disks, turn on write ahead logging in the `sqlite` connection of `config/database.php` (`'journal_mode' => 'wal'`, `'busy_timeout' => 5000`); on network storage, leave the defaults.
- **Web folder.** Point the domain's web folder at the app's `public/` folder in the hosting panel. If the panel can't, put a `.htaccess` in the app's root that forwards everything to `public/` (`RewriteEngine On` and `RewriteRule ^(.*)$ public/$1 [L]`), and keep `.env` and the rest of the app unreadable from the web.
- **Page cache.** Most shared hosts run Apache with `mod_rewrite` and `mod_headers`. Print the rules with `php artisan lynx:page-cache:server apache` and add them to `public/.htaccess`: stored pages are then sent without PHP, with either web folder above. If the host doesn't allow them, use the `serve.php` line instead ([Page cache](/docs/front-end/page-cache#sending-pages-without-php)).
- **PHP version on the command line.** The `php` command over SSH and in cron may be an older version than the site uses. Call PHP 8.5 by its full path (the host's documentation gives it), in the cron entry too.
- **Composer.** Shared servers stop processes that use too much memory, and `composer install` can be one of them. Install locally or in CI with `composer install --no-dev --optimize-autoloader` and upload the `vendor` folder.
- **Queues.** A shared plan can't keep a queue worker running. Set `QUEUE_CONNECTION=sync` so form notifications are sent during the request, or let cron run `php artisan queue:work --stop-when-empty` every minute.
- **Extensions.** Check with `php -m` that `pdo_sqlite` and `gd` (or `imagick`, for image fields) are there, besides those Laravel needs.

A shared server divides its processor among many customers, so the numbers in [Server capacity](/docs/front-end/server-capacity) don't apply. With the page cache on, most visitors never reach PHP, which is what makes cheap hosting work. Once the site needs PostgreSQL, a queue worker or steady performance, move to a VPS with root access.

## Developing against a local checkout

To build a site against a local clone of the Lynx monorepo, point Composer at its packages with a path repository. In the site's `composer.json`:

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

Then `composer require lynxcms/core:@dev lynxcms/admin:@dev lynxcms/menus:@dev`. The admin's CSS and JS are not committed in the monorepo, so build them once in the checkout, and again after pulling changes to the admin's views: `npm --prefix packages/admin ci && composer assets`. The packages require each other at the same version, so every one of them needs a version in `options.versions`. With `symlink`, changes in the checkout show up in the site at once.

## The user model

The admin uses your app's user model. For sign-in, password reset, two-factor authentication and passkeys it needs:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;
}
```

See [Sign-in and accounts](/docs/admin/sign-in).
