---
title: Page passwords
description: Pages, or whole branches, that visitors open only with a password set on the page.
order: 8
---

# Page passwords

The `lynxcms/page-password` module (pagePassword) asks visitors for a password before showing a page. The password is set on the page, and the pages below it share it. There are no visitor accounts: anyone who knows the password can open the page.

## Setting up

```bash
composer require lynxcms/page-password
```

Then choose the templates whose pages may have a password, in **Settings > Page passwords**, or with the action (also an MCP and agent tool):

```php
lynx()->run(\LynxCms\PagePassword\Actions\EnablePagePassword::class, ['template' => 'members']);
```

The template gets a **Password** field in the page editor's sidebar. Editors type a password, or press **Generate**, and **Show** reveals it so they can send it to guests. An empty field means the page is public. Turning a template off keeps the passwords, but its pages become public until it is turned on again.

## How protection works

- A page with a password protects itself and every page below it.
- A page below with its own password uses that one instead: the nearest password wins.
- Visitors who open a protected page see a password form (HTTP 403). The right password unlocks the page that holds it, and the pages below it, for 30 days in that browser (`lynx-page-password.remember_days`, or `LYNX_PAGE_PASSWORD_REMEMBER_DAYS`). Changing the password locks it again for everyone.
- Wrong passwords show an error; after five tries in a minute from one address, the form asks the visitor to wait. A page also takes at most 100 wrong passwords an hour from all addresses together (`LYNX_PAGE_PASSWORD_MAX_FAILURES`); past that it refuses every try, the right one too, until the hour is over, and the log says so. Choose a passphrase of several words rather than a short code, since guests share it and anyone can try.
- Until a page is unlocked, it is left out of everything visitors see: selectors (`find`, `count`, `children`), search, page reference fields and menus.
- Protected pages never appear in the sitemap, are sent with `X-Robots-Tag: noindex` and are never cached (`Cache-Control: no-store, private`), unlocked or not.
- Signed-in users see the protected pages their roles let them view in the admin, without the form.
- The admin, MCP, agents, exports and backups always see every page.

In the admin, protected pages have a key in the page tree, and pages protected from above link to the page that holds the password.

## In templates

The field reads as a `StoredPassword`:

```blade
@if ($page->page_password->isSet())
    <p>This page is for members.</p>
@endif
```

`isSet()`, `matches($typed)` (in constant time) and `reveal()` (the password itself, for code that sends it to guests) are available. The value is stored encrypted with the app key; changing `APP_KEY` keeps the pages locked, but their passwords must be set again.

## The form

The form is a small page of its own, in the visitor's language (English, French and Portuguese ship with the module). To match your site, publish the view and edit it:

```bash
php artisan vendor:publish --tag=lynx-page-password-views
```

Its texts can be changed, or added for other languages, in `lang/vendor/lynx-page-password/{locale}/form.php`, with the keys of the module's `lang/en/form.php`. `--tag=lynx-page-password-config` publishes the configuration.

The form posts to `POST /lynx-page-password/unlock` with `page`, `password` and `language`, and always returns to the page itself.

## Limits

- Files and images of a protected page keep public addresses, as media addresses always are.
- `lynx()->pages->get()` is not filtered, so a template that fetches a protected page by its path can still print it. Selectors and page reference fields are filtered.
- The protection is a shared password, not an account: anyone who has it can pass it on. Change it to take access away.
