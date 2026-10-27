---
draft: true
title: Build a Bilingual Website
description: A guide in seven chapters to a law firm's website in English and French, built with Lynx, from the page tree to search, SEO and the page cache.
---

# Build a Bilingual Website

In this guide you build the website of **Castor & Otters, Inc.**, a made-up wildlife law firm with offices in Vancouver and Montreal. The site is in English and French. It presents the firm's practice areas, its team, its cases and news, and its two offices. Visitors can search it and send a consultation request, and the firm's staff change every text, menu and page themselves in the admin.

Each chapter adds one part of the site. By the end you have used the parts of Lynx that almost every website needs: templates and fields, pages in two languages, Blade views, selectors, menus, editable texts, a form, search, SEO and the page cache.

## What you build

| Chapter | You add | The firm gets |
| --- | --- | --- |
| 1. [Plan and install](./install) | A Laravel app with Lynx and its modules | An admin to sign in to |
| 2. [Templates and fields](./content-model) | English and French, and the kinds of pages the site has | Editing forms that fit each kind of page |
| 3. [Content in two languages](./content) | The firm's pages, written in both languages | A site full of real content, translated where it needs to be |
| 4. [Views and the layout](./views) | The layout, the menus, the language switcher and editable texts | A site that looks finished, with menus and texts they change |
| 5. [Listing and linking pages](./connected-pages) | Lists of pages, and pages that point at each other | A team page, news, and lawyers linked to their practice areas |
| 6. [A contact form and search](./contact-search) | A consultation request and a search page | Requests in their inbox, and a site visitors can search |
| 7. [SEO and launch](./launch) | Search engine tags, the sitemap and the page cache | A fast site that shows well in search results |

## The finished code

This site is the website part of a starter app you can clone: [LynxCMS/starter-meeting-rooms](https://github.com/LynxCMS/starter-meeting-rooms). The starter also books the firm's meeting rooms, which the [Meeting Rooms guide](/guides/meeting-rooms/) explains; this guide leaves the rooms out.

The code in this guide comes from the starter, shortened so each example shows one thing: most styling classes are removed, and long lists keep a few items. Where the guide's code differs from the starter's for another reason, the guide says why.

To look at the finished site while you read:

```bash
git clone https://github.com/LynxCMS/starter-meeting-rooms.git castor-otters
cd castor-otters
composer setup
php artisan db:seed
composer dev
```

The site is at `http://localhost:8000`. Sign in to the admin at `/lynx` as `owner@meeting-rooms.example`, with the password `change-me-now`.

## Before you start

You need PHP 8.5, [Composer](https://getcomposer.org) (PHP's package manager) and [Node.js](https://nodejs.org), which builds the site's CSS. You should be comfortable with PHP. You don't need much Laravel: when the guide relies on a Laravel feature, it explains it in a sentence or two, and links to [Laravel's documentation](https://laravel.com/docs) for more.

If you have never used Lynx, [Your First Site](/getting-started/first-site) builds a smaller site in about thirty minutes, and [Core Concepts](/getting-started/concepts) explains the ideas this guide uses.
