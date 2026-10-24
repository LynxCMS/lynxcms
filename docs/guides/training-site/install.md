---
title: Plan and Install
description: Chapter 1 of the training site guide. The site map, a Laravel app with Lynx and its modules, and the first superuser.
---

# Plan and Install

## The site map

Atlas Training has a home page, a course catalogue, a page per course with its dated sessions, instructors, studios, news and a few plain pages. In Lynx each of those is a page in one tree, and each page uses a **template** that says which fields it has:

```
/                              home
├── courses/                   courses (the catalogue)
│   └── yoga-teacher-training/ course
│       └── 2026-11-02-lisbon/ session (one dated run, which people book)
├── instructors/               instructors
│   └── ana-ribeiro/           instructor
├── locations/                 locations
│   └── lisbon/                location
├── posts/                     posts (news)
├── about/                     page
└── privacy/                   page
```

Every page also has a Portuguese URL, such as `/pt/cursos/formacao-professores-yoga`, which editors set in the admin.

## Create the app

```bash
composer create-project laravel/laravel atlas
cd atlas
```

Require Lynx and the modules the site uses:

```bash
composer require lynxcms/core lynxcms/admin lynxcms/menus lynxcms/media \
    lynxcms/forms lynxcms/seo lynxcms/sitemap lynxcms/cache lynxcms/mcp
```

| Package | Gives Atlas |
| --- | --- |
| `lynxcms/core` | Pages, templates, fields, languages, selectors, actions. |
| `lynxcms/admin` | The admin at `/lynx`, for the school's staff. |
| `lynxcms/menus` | The main and footer menus, edited in the admin. |
| `lynxcms/media` | Photos of courses, instructors and studios. |
| `lynxcms/forms` | Enquiries, newsletter sign-ups and surveys (chapters 6 and 9). |
| `lynxcms/seo`, `lynxcms/sitemap` | Search titles, structured data and the sitemap (chapter 5). |
| `lynxcms/cache` | The page cache that makes the site fast (chapter 10). |
| `lynxcms/mcp` | MCP tools so the owner's AI assistant can work with the site (chapter 10). |

Composer lists the packages with database changes waiting. Run the migrations, then install Lynx:

```bash
php artisan migrate
php artisan lynx:install
```

`lynx:install` creates the default language, the home page and three roles: `superuser`, `editor` and `guest`, then asks for the owner's name, email and password and creates their account as superuser. In chapter 8 customers sign up too, and each new account gets the `guest` role, which sees the website and never the admin. See [People who sign up](/admin/permissions#people-who-sign-up).

## Hand the home page to Lynx

Lynx serves every URL your routes leave free, but Laravel starts with a welcome route at `/`. Empty `routes/web.php`:

<<< @/snippets/training/routes/web.php{php}

Sign in at `/lynx` and you see the page tree with the home page. Visit `/` and Lynx renders it with its default view, a list of its fields, until chapter 3 writes the real one.

## What you get

- **The client:** an admin they can sign in to now, with roles ready for their staff.
- **You:** a plain Laravel app. Lynx is packages and an action layer, so everything you know about Laravel still applies.
