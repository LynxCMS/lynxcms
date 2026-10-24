---
title: Members Area
description: Chapter 8 of the training site guide. Sign-up and sign-in with the site's own Fortify, a members area with the member's bookings, and course materials only for the people who booked.
---

# Members Area

Customers who booked want somewhere to go back to: their bookings, and the materials of their course. This chapter lets visitors create accounts on the site, gives them a members area, and opens each course's materials only to the people who booked it.

Members and staff share one users table and one sign-in. **Roles** decide who opens the admin, and new accounts get none that do.

## Sign-up and sign-in

The site signs everyone in with Laravel Fortify, at the site's root: `/login`, `/register`, `/forgot-password`. Publish its config with registration on:

<<< @/snippets/training/config/fortify.php{php}

Because `config/fortify.php` exists, Lynx's admin stops showing its own sign-in screens and uses these (`LYNX_ADMIN_AUTH=auto`). Staff sign in at `/login` too, and a staff member who opens `/lynx` while signed out comes back to the admin after signing in. Lynx keeps inviting staff from **Access › Users**, and keeps the admin's **Account security** screen. See [Who signs users in](/admin/sign-in#who-signs-users-in).

A provider gives Fortify the site's screens and its sign-up action:

<<< @/snippets/training/app/Providers/FortifyServiceProvider.php#provider{php}

The screens are Blade views in the Bloom style, around one card. The sign-in screen, for example:

<<< @/snippets/training/resources/views/auth/login.blade.php{blade}

## Who gets what

When someone signs up, Fortify dispatches Laravel's `Registered` event, and Lynx gives the new account the **guest** role. A guest has no grants: they see the website and their members area, and the admin answers 403. You did nothing for this beyond running `lynx:install` in chapter 1, which made the owner a superuser. See [People who sign up](/admin/permissions#people-who-sign-up).

## The members area

Fortify sends members to `/account` after they sign in. It is a route of the app's, listing the member's bookings:

<<< @/snippets/training/routes/web.php#account{php}

<<< @/snippets/training/app/Http/Controllers/AccountController.php#account{php}

A booking made while signed in carries the member's id (chapter 7's controller stores `$request->user()`), so it appears here. Bookings are matched by account, never by email address alone: without email verification, anyone could sign up with someone else's address.

## Materials for the people who booked

Each course has a child page on the `materials` template: reading lists, preparation, links. Two pieces keep it for the people who booked:

- A **visibility rule** leaves materials pages out of everything visitors find (lists, menus, search, page references) unless they booked that course.
- A **page gate** guards the URL, and the page's private downloads: guests are sent to sign in, and members who didn't book see what the page is for.

<<< @/snippets/training/app/Members/CourseMaterials.php#rule{php}

<<< @/snippets/training/app/Providers/AtlasServiceProvider.php#members{php}

`audience()` tells Lynx's caches that members see different things, so a copy of a page made for one member is never served to another. [Page Access](/deeper/visibility) explains rules and gates in full.

### Downloads only for them

Materials pages have a `downloads` field with **Private** on (chapter 2's seeder sets it). Files in a public field have public addresses, which anyone with a link could share. A private field's files live off the public disk, and their links go through Lynx, which asks the materials page's own questions. People who booked get the file. Guests are sent to sign in by the same gate, and other members see what the page is for.

```php
lynx()->fields->add('downloads', 'files', label: 'Downloads', settings: ['private' => true, 'extensions' => 'pdf zip mp3 mp4']);
```

The view lists them like any files, and `$file->url()` gives the private address:

<<< @/snippets/training/resources/views/templates/materials.blade.php{blade}

Staff upload the files in the page editor as usual. See [Private files](/basics/images-and-files#private-files).

## What you get

- **The client:** customers who sign up, find their bookings and get their course materials without emailing the office.
- **You:** one sign-in for members and staff, roles that keep members out of the admin by default, and access rules that apply everywhere a page can show up.
