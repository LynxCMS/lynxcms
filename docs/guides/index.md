---
title: Guides and Starters
description: Guides that build a whole site or app with Lynx, one chapter at a time, and the starter apps that hold their finished code.
---

# Guides and Starters

A **guide** builds a whole site or app with Lynx, one chapter at a time. A **starter** is a finished Laravel app built with Lynx: you can install one in a few minutes, change it, and read how it works.

## Guides

| Guide | Builds | Chapters |
| --- | --- | --- |
| [Build a Training Company Site](/guides/training-site/) | A school's site in English and Portuguese that brings in leads, takes paid bookings and serves members. | 10 |
| [Build a Meeting Rooms App](/guides/meeting-rooms/) | A law firm's room booking app, where rooms are pages and bookings are actions shared by the admin, lobby screens and AI assistants. | 8 |

Read [Your First Site](/getting-started/first-site) before either one. Each guide's finished code is one of the starters below.

## Starters

Each starter needs PHP 8.5, Composer, Node and Git, and keeps its data in SQLite, so there is no database server to set up. On macOS or Windows, [Laravel Herd](https://herd.laravel.com/) installs all of them.

### Arcturus Systems

![The Arcturus Systems home page: a menu, a hero about a summit in Lisbon, and a row of recommended pages](/img/starters/arcturus.webp)

The website of an invented software company, in English and Portuguese. Editors arrange the home page section by section, and pick the pages each section shows. It also has a mega-menu, a hero carousel whose slides publish on a schedule, a search panel and bookmarks.

- [Live demo](https://demo-corporate.lynxcms.org/), in English and [Portuguese](https://demo-corporate.lynxcms.org/pt)
- [Demo admin](https://demo-corporate.lynxcms.org/lynx): choose **Admin** or **Editor** on the sign-in page, with no password. The demo returns to its original content every hour.
- [Code and setup notes](https://github.com/LynxCMS/lynx-corporate-starter)

```bash
git clone https://github.com/LynxCMS/lynx-corporate-starter.git arcturus
cd arcturus
composer setup
# set OWNER_PASSWORD in .env
php artisan db:seed
composer dev
```

### Atlas Training

![The Atlas Training home page](/img/atlas-home.png)

The site of an invented school that trains yoga, pilates and nutrition teachers in Lisbon and Porto, in English and Portuguese. It presents the courses, collects leads, takes paid bookings with Stripe and has a members area. [Build a Training Company Site](/guides/training-site/) builds it chapter by chapter, and every file the guide shows runs in Lynx's test suite.

- [Code](https://github.com/LynxCMS/starter-training)

```bash
git clone https://github.com/LynxCMS/starter-training.git atlas
cd atlas
composer setup
php artisan db:seed
composer dev
```

### Castor & Otters

![The Castor & Otters home page: a wildlife law firm's headline beside drawings of a beaver and an otter](/img/starters/castor-otters.webp)

An invented wildlife law firm in Vancouver and Montreal, in English and French. Its website and its meeting rooms app are one Laravel app. Staff book rooms on the site, in the admin or by asking an AI assistant, and screens in the lobbies and at each door show which rooms are free. [Build a Meeting Rooms App](/guides/meeting-rooms/) explains how it is built.

- [Live demo](https://meeting-rooms.lynxcms.org/), with its [room screens](https://meeting-rooms.lynxcms.org/demo/screens)
- [Demo admin](https://meeting-rooms.lynxcms.org/lynx)
- [Code](https://github.com/LynxCMS/starter-meeting-rooms)

```bash
git clone https://github.com/LynxCMS/starter-meeting-rooms.git castor-otters
cd castor-otters
composer setup
php artisan db:seed
composer dev
```

## Choosing a starter

- **A website editors run themselves:** Arcturus Systems. Most of it is changed from the admin.
- **A business site that sells:** Atlas Training, with leads, payments and members.
- **An app with a website:** Castor & Otters, where Lynx's pages and actions hold the app's own data and rules.
