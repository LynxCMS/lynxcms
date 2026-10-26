---
title: Plan and Install
description: Chapter 1 of the meeting rooms guide. The page tree, a Laravel app with Lynx, its modules and Laravel Zap, and roles for staff and office managers.
---

# Plan and Install

## The page tree

Everything editors manage is a page in Lynx's tree: the firm's website, and the parts of the app that people describe rather than compute. Rooms, screens and lobby announcements are pages, so office managers add a room with a photo and opening hours the way they add a news item. Bookings are not pages: they are many, they change by the minute, and they belong to Zap's tables.

```
/                         home
├── offices/              offices
│   ├── vancouver/        office (time zone: Pacific)
│   └── montreal/         office (time zone: Eastern)
├── rooms/                rooms (behind a page password)
│   ├── lodge/            room (office: Vancouver)
│   └── huard/            room (office: Montreal)
├── screens               screens (no URL)
│   ├── Vancouver lobby   screen (kind: lobby)
│   └── The Lodge door    screen (kind: door, room: The Lodge)
├── announcements         announcements (no URL)
└── settings              settings (the screens' logo and accent)
```

Templates without a URL (`hasUrl: false`) keep screens and announcements out of the website, its menus, search and sitemap, while editors still find them in the tree.

## Create the app

```bash
composer create-project laravel/laravel castor-otters
cd castor-otters
composer require lynxcms/core lynxcms/admin lynxcms/menus lynxcms/media lynxcms/forms \
    lynxcms/seo lynxcms/sitemap lynxcms/cache lynxcms/page-password lynxcms/mcp \
    laraveljutsu/zap laravel/sanctum spatie/icalendar-generator bacon/bacon-qr-code
```

| Package | Gives the firm |
| --- | --- |
| `lynxcms/core`, `lynxcms/admin` | Pages, actions and the admin at `/lynx`. |
| `lynxcms/page-password` | One password on the Rooms section for visitors. |
| `lynxcms/mcp`, `laravel/sanctum` | The MCP server for staff's AI assistants, behind personal tokens (chapter 7). |
| `laraveljutsu/zap` | Rooms' availability, bookings, blocked times, conflicts and free slots. |
| `spatie/icalendar-generator` | The `.ics` invites and calendar feeds (chapter 6). |
| `bacon/bacon-qr-code` | A QR code of each screen's link, to open it on a TV (chapter 4). |

The other modules serve the website, as in the training guide. Publish Zap's migrations and config, then install Lynx with a first superuser:

```bash
php artisan vendor:publish --tag=zap-migrations
php artisan vendor:publish --tag=zap-config
php artisan migrate
php artisan lynx:install --user=1
```

## The app's own provider

The app's wiring lives in one provider, `App\Providers\RoomsServiceProvider`, added to `bootstrap/providers.php`. Each chapter adds its part there. It starts with two permissions:

```php
public function boot(): void
{
    lynx()->permissions()->register('rooms.book', 'Book meeting rooms, and change or cancel your own bookings');
    lynx()->permissions()->register('rooms.manage', 'Change or cancel anyone\'s bookings, block rooms, and link screens and room calendars');
}
```

Registered permissions show in the admin's role editor next to Lynx's own, and the booking actions check them in chapter 3.

## Roles

Two roles cover the firm. Staff book rooms and never open the admin. Office managers open the admin, edit pages and manage everyone's bookings. The seeder makes them through Lynx's role action, so they land in the activity log like any other change:

```php
lynx()->run(SaveRole::class, ['name' => 'staff', 'label' => 'Staff', 'grants' => [['permission' => 'rooms.book']]]);
lynx()->run(SaveRole::class, ['name' => 'manager', 'label' => 'Office manager', 'grants' => [
    ['permission' => 'admin.access'], ['permission' => 'pages.view'], ['permission' => 'pages.edit'],
    ['permission' => 'rooms.book'], ['permission' => 'rooms.manage'],
]]);
```

People join by invitation from the admin's Users screen, which sends a link to set a password. There is no registration: an office app knows its staff. See [Sign-in and accounts](/admin/sign-in).

## Time zones

Each office page has a time zone field, and each room an office. A booking's times are wall-clock times in its room's office: 10:00 in Montreal is 10:00 on the Montreal door screen and in the Montreal staff's calendars. The app reads the zone through the room:

```php
public function timeZone(): string
{
    $value = $this->page()?->office?->time_zone?->value;
    $zone = SiteSchemaSeeder::TIME_ZONES[(string) $value]['zone'] ?? null;

    return $zone ?? Value::string(config('app.timezone'), 'UTC');
}
```

"Today", "already passed" and "now" on a screen are all read in that zone, and `APP_TIMEZONE` stays `UTC`.

## What you get

- **The firm:** an admin where staff and office managers sign in with the rights they need, nothing more.
- **You:** a plain Laravel app with one provider to read, and a page tree that already tells you what editors own and what the app computes.
