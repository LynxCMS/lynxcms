---
title: Upgrade Guide
description: How to update Lynx, and what to check between releases.
order: 2
---

# Upgrade Guide

## Updating

Update every Lynx package together, since they share one version:

```bash
php artisan lynx:backup                 # a content package, in case you need to compare
composer update "lynxcms/*" --with-all-dependencies
php artisan lynx:migrate                # asks whether the database is backed up
php artisan optimize                    # if you cache config, routes and views
```

After `composer update`, Composer lists any Lynx package with database changes waiting. `lynx:migrate` runs only Lynx's migrations, and `--force` skips its question in deploy scripts. Back up the database first; a content package is not a database backup (see [Backups](/deeper/schema-and-content#backups)).

## To the next release

No changes needed. If you start new environments from a content package, use the new order: migrate, `lynx:import --with-schema`, then `lynx:install --user=…` (see [Content packages](/deeper/schema-and-content#content-packages)). Running `lynx:install` before the import still works, but it leaves the installed default language, home template and `title` field with uuids your schema files don't know.

## From 0.1.1 to 0.1.2

No changes needed for most sites. Check these if they apply to you:

- **Scripts that delete templates.** `templates.delete` now refuses with the pages that use the template, in a longer message. If you parse the message, match on the start, "Template [name] is used by".
- **Fields created on one-language sites** are now translatable when their type allows it (text, textarea and URL). Existing fields keep their setting. If you create fields from code with `translatable: false`, nothing changes.
- **A monorepo clone** (only when you work on Lynx itself): `packages/admin/dist` is no longer committed. Run `npm --prefix packages/admin ci` once and `composer assets` to build it. Installs from Packagist are unaffected.

## From 0.1.0 to 0.1.1

- **Run `php artisan lynx:migrate`** if you installed a module with migrations (page cache, forms, media and others) without running `migrate` afterwards. Its screens answered 500 until then.
- **Module authors:** register admin screens with `Admin::screen()` or a controller, not a closure. A closure route written on the same line as `Admin::routes()` breaks once routes are cached (see [Extending the admin](/admin/extending#a-new-screen)).
