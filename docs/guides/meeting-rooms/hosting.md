---
title: Hosting
description: Chapter 8 of the meeting rooms guide. The server, the environment, the scheduler and queue worker, deploys, backups, screens and assistants in production.
---

# Hosting

One Laravel app serves the website, the staff's pages, the admin, the screens, the calendar feeds and the MCP server. It runs wherever Laravel runs, and SQLite is enough for an office of this size (about 10 rooms, a few screens, under 100 staff).

## The server

- PHP 8.5 with the extensions Laravel needs plus `intl` and `pdo_sqlite`, a web server whose root is `public/`, and HTTPS. Screens ask the browser to stay awake and assistants send their tokens, which both need HTTPS. Node is only needed to build the CSS and JavaScript.
- The server's clock must be right (a time server, such as `systemd-timesyncd` or `chrony`): screens, check in, no-shows and reminders all read it.
- The web server's user writes `storage/`, `bootstrap/cache/` and the `database/` folder (SQLite writes a journal next to the file).

## The environment

| Setting | In production |
| --- | --- |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `APP_URL` | The firm's address, with `https://`. Kiosk links, feed addresses, invites and the assistants' setup use it. |
| `APP_TIMEZONE` | Leave it at `UTC`. Each room takes its time zone from its office's page. |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS` | A real mailer. The from address is the rooms' mailbox and the organizer of every invite, so calendar apps accept its updates; replies to it are not read. |
| `LYNX_PAGE_CACHE` | `true`. Guests get stored pages; the rooms, kiosk links, feeds, staff pages and the MCP server are never stored. |
| `LYNX_BACKUPS_SCHEDULE` | `daily`, for Lynx's content backups (`lynx:backup`, 14 kept). |
| `LYNX_MCP_WEB` | `true` lets staff connect AI assistants; `false` turns the MCP server off. |
| `ROOMS_*` | The booking rules, reminders and screens (`config/rooms.php`). |

## Processes

The scheduler runs every minute from cron. It releases no-shows, sends reminders, publishes scheduled pages and copies the database every night:

```cron
* * * * * cd /var/www/castor-otters && php artisan schedule:run >> /dev/null 2>&1
```

A queue worker sends the meeting mail and the consultation requests. Keep it running with Supervisor or systemd:

```bash
php artisan queue:work --tries=3
```

## Deploying

The first time, set `ROOMS_OWNER_PASSWORD` and seed. The seed is a demo: it also creates staff accounts with the password `password` and a few meetings. Delete those accounts under Users in the admin and invite the real staff (Lynx sends invitations; there is no registration).

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Run the same steps for each new version. Templates and fields are made by the seeders; once the site is live, change them in the admin, or export them with `php artisan lynx:schema:export` in development and run `php artisan lynx:schema:apply --dry-run`, then `lynx:schema:apply`, on the server.

## Backups

Two kinds, both kept on the server, so copy them elsewhere too:

- `php artisan rooms:backup-database` (every night at 02:30) copies the SQLite database into `storage/app/private/backups`, keeping 14 (`ROOMS_DATABASE_BACKUPS_KEEP`). It holds everything: pages, bookings, accounts, kiosk links and tokens. To restore, stop the queue worker and put a copy in place of `database/database.sqlite`.
- `php artisan lynx:backup` writes the schema and the pages as a content package, which `lynx:import` brings into another Lynx site. It leaves out bookings and accounts.

## Screens

Open each screen's kiosk link on its TV or tablet in a full screen browser, for example `chromium --kiosk --noerrdialogs --disable-session-crashed-bubble https://firm.example/screens/…`, and turn off the device's own sleep. A kiosk link is the screen's only key: issue a new one if a device is lost, which cuts off the old. Screens reload themselves every night at 3:00 to pick up new code.

## Assistants

Staff connect their assistant with the token from My bookings, over HTTPS only. Each token acts as its owner, with their roles, and is revoked from the same page. Managers revoke a person's access by removing their roles or their account.

## What you get

- **The firm:** an app that runs on one ordinary server, keeps its screens up, mails on time and can be restored from last night's copy.
- **You:** a deploy that is the same five commands every time, and nothing to run beside Laravel's own scheduler and queue worker.
