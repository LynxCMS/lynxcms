---
draft: true
title: Plan and Install
description: Chapter 1 of the bilingual website guide. The site's pages, a Laravel app with Lynx and its modules, and the first account.
---

# Plan and Install

Before writing code, it helps to sketch the site's pages. In Lynx every page of a site sits in one **tree**, like files in folders, and each page uses a **template**: a kind of page, such as a lawyer or a news item, with the fields that kind of page holds.

## The site's pages

Castor & Otters has a home page, five sections and a few pages of their own:

```
/                          home
├── practice-areas/        practice-areas   (what the firm does)
│   └── water-rights/      practice-area
├── team/                  team
│   └── castor-belanger/   lawyer
├── news/                  news             (cases and news)
│   └── elk-creek-lodge/   post
├── offices/               offices
│   └── vancouver/         office
├── contact/               contact
├── search/                search
├── legal/                 page
└── privacy/               page
```

The left column is each page's address, and the right column its template. The tree gives each page its address: the lawyer `castor-belanger` under the team page lives at `/team/castor-belanger/`. Every page also has a French address, such as `/fr/equipe/castor-belanger/`, which the next chapters set.

## Creating the app

Lynx is a set of packages you add to a Laravel app. Create a new app with Composer:

```bash
composer create-project laravel/laravel castor-otters
cd castor-otters
```

This makes a folder with a working Laravel app. It stores its data in SQLite, a database kept in one file (`database/database.sqlite`), so you don't need a database server.

## Installing Lynx

Add Lynx's core, its admin, and the modules the site uses:

```bash
composer require lynxcms/core lynxcms/admin lynxcms/menus lynxcms/media \
    lynxcms/forms lynxcms/seo lynxcms/sitemap lynxcms/cache
```

| Package | What the firm's site uses it for |
| --- | --- |
| `lynxcms/core` | Pages, templates, fields, languages and selectors. |
| `lynxcms/admin` | The admin at `/lynx`, where the firm's staff work. |
| `lynxcms/menus` | The main menu and the footer menu, which staff edit (chapter 4). |
| `lynxcms/media` | The lawyers' photos (chapter 5). |
| `lynxcms/forms` | The consultation request (chapter 6). |
| `lynxcms/seo`, `lynxcms/sitemap` | Titles and descriptions for search engines, and the sitemap (chapter 7). |
| `lynxcms/cache` | The page cache, which makes the site fast (chapter 7). |

Then create Lynx's database tables and install it:

```bash
php artisan migrate
php artisan lynx:install
```

`php artisan` runs Artisan, Laravel's command line tool. `migrate` creates the database tables of your app and of Lynx. `lynx:install` creates the default language, the home page and the roles, then asks for your name, email and a password, and makes you the site's **superuser**: the account that may do everything.

## Handing the home page to Lynx

Laravel decides which code answers each address with **routes**, listed in `routes/web.php`. A new app has one route, for its welcome page at `/`. Lynx answers every address your routes leave free, so remove that route to let Lynx show the home page:

```php
<?php

// routes/web.php: the site's pages are Lynx's, so there are no routes here yet.
```

Start the app with `php artisan serve` and open `http://localhost:8000/lynx`. After signing in you see the page tree, with only the home page in it. Visit `http://localhost:8000/`: Lynx shows the home page with a plain built-in view until chapter 4 writes a real one.

## What you have now

- **The firm:** an admin they can sign in to.
- **You:** an ordinary Laravel app with Lynx installed, and a plan of the pages to build.
