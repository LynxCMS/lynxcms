---
title: Build a Meeting Rooms App
description: A guide in eight chapters to an app built on Lynx, where rooms are pages and bookings are actions shared by the admin, screens, staff pages and AI assistants.
---

# Build a Meeting Rooms App

The [training site guide](/guides/training-site/) builds a website. This one builds an application on Lynx: **Castor & Otters, Inc.**, a law firm in Vancouver and Montreal, books its meeting rooms on the site, shows them on a TV in each lobby and a tablet at each door, mails invites to calendars, and lets staff ask their AI assistant for "a room for six with video tomorrow at 10".

Most of that is ordinary Laravel, with [Laravel Zap](https://www.laravel-zap.com/) doing the calendar arithmetic. Lynx gives it three things an app usually builds by hand:

- **Rooms are pages.** Office managers add a room, its photo, seats and opening hours in the admin, in two languages, with drafts and revisions. The app keeps a small Zap model in step with each room page.
- **Bookings are actions.** One class per change (book, move, cancel, block, check in) holds the rules, the permission, the dry run and the activity log entry. The admin, the booking page, the door screens and AI assistants all run the same classes, so a rule written once holds everywhere.
- **The admin is extensible.** The bookings week view, the Bookings tab on a room and the Kiosk link tab on a screen are a few lines each.

The finished app is a starter you can clone: [LynxCMS/starter-meeting-rooms](https://github.com/LynxCMS/starter-meeting-rooms). This guide shows the parts that matter, shortened to what each section explains; the starter has the full classes. When Lynx releases a new version, the starter is updated to it and its tests run, so its code works with the release you install.

## What you build

| Chapter | You add | The firm gets |
| --- | --- | --- |
| 1. [Plan and install](./install) | A Laravel app with Lynx, its modules and Zap; roles | An admin with staff and office managers |
| 2. [Rooms are pages](./rooms) | Room templates, a Zap model kept in step with each page | Rooms they edit like any page |
| 3. [Bookings are actions](./bookings) | The booking rules as Lynx actions | Rules that hold whoever books, and a log of every change |
| 4. [The admin](./admin) | A week view, editor tabs, kiosk links | One place to see and change every booking |
| 5. [Screens](./screens) | Lobby and door screens on secret links | Rooms that show when they are free, and release no-shows |
| 6. [Staff pages, mail and calendars](./staff) | A booking page, invites with `.ics`, reminders, feeds | Staff who book for themselves, in their own calendar |
| 7. [AI assistants](./assistants) | Read tools and personal tokens for MCP | Booking by asking |
| 8. [Hosting](./hosting) | The server, processes, deploys and backups | An app that is safe to run |

The firm's website itself (two languages, menus, the consultation form, SEO, search and the page cache) uses what the [training site guide](/guides/training-site/) explains, so this guide leaves it out. The starter has all of it.

## Before you start

You need PHP 8.5, Composer and Node. You should know basic Laravel: routes, Blade, Livewire, service providers and seeders, and have read [Your First Site](/getting-started/first-site) and [Actions and Hooks](/deeper/actions-and-hooks).

To follow along with the finished code open beside you:

```bash
git clone https://github.com/LynxCMS/starter-meeting-rooms.git castor-otters
cd castor-otters
composer setup
php artisan db:seed
composer dev
```

The site is at `http://localhost:8000`. Sign in at `/lynx` as `owner@meeting-rooms.example` with the password `change-me-now`, or as `ana@meeting-rooms.example` (staff) or `mia@meeting-rooms.example` (an office manager) with `password`.
