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

## From 0.1.16 to 0.1.17

- A user without a role no longer has every right while no one holds a role. If nobody on your site has a role yet, nobody can open the admin after the update: run `php artisan lynx:install --user=ID` with your user's id. Sites that already have a superuser see no change.
- Web requests with nobody signed in run actions as a visitor, who may only run actions open to everyone. Code that writes on a visitor's behalf (a webhook, a public form of your own) must say so with `lynx()->actingAs(Actor::system(), fn () => ...)`; without it, it now fails with an authorization error.
- `media.store` no longer downloads from hosts on your own network. If an importer downloads from one, add it to `downloads.allowed_hosts` in `config/lynx-media.php`.
- MCP clients and `lynx:ask` acting for no user need `LYNX_ANONYMOUS_ROLE` on every site, as they already did on sites with roles.

## From 0.1.15 to 0.1.16

No changes needed. Public fields keep their files and URLs. Before turning a field private on a server with several machines, make sure `LYNX_MEDIA_PRIVATE_DISK` is a disk every machine reads, such as S3 without public access.

## From 0.1.14 to 0.1.15

No changes needed.

## From 0.1.13 to 0.1.14

No changes needed.

## From 0.1.12 to 0.1.13

No changes needed.

## From 0.1.11 to 0.1.12

- With `lynxcms/forms` installed, existing sites get the new form setting the next time `forms.install` runs, for example when someone creates a form in the admin. To add it now: `php artisan tinker --execute="lynx()->run(LynxCms\\Forms\\Actions\\InstallForms::class)"`.

## From 0.1.10 to 0.1.11

No changes needed. Strings in `lang/pt.json` now show on `pt-PT` pages; if you copied them into `lang/pt-PT.json` to work around it, you can remove the copies.

## From 0.1.9 to 0.1.10

- With `lynxcms/forms` installed, run `php artisan lynx:migrate`: submissions get a `user_id` column.

## From 0.1.8 to 0.1.9

No changes needed.

## From 0.1.7 to 0.1.8

No changes needed.

## From 0.1.6 to 0.1.7

No changes needed.

## From 0.1.5 to 0.1.6

No changes needed.

## From 0.1.4 to 0.1.5

No changes needed. If your app has its own `config/fortify.php`, the Users screen offers invitations again, and the admin's user menu links to Account security.

## From 0.1.3 to 0.1.4

- Run `php artisan lynx:install` once. It is safe to run again, and adds the new `guest` role to an existing site.
- If your site lets visitors sign up, make sure someone is a superuser (`php artisan lynx:install --user=ID`) before the next sign-up: it turns the permission checks on for everyone.
- If your schema files are applied with `--force`, export them again so they include the `guest` role.

## From 0.1.2 to 0.1.3

No changes needed. If you start new environments from a content package, use the new order: migrate, `lynx:import --with-schema`, then `lynx:install --user=…` (see [Content packages](/deeper/schema-and-content#content-packages)). Running `lynx:install` before the import still works, but it leaves the installed default language, home template and `title` field with uuids your schema files don't know.

## From 0.1.1 to 0.1.2

No changes needed for most sites. Check these if they apply to you:

- **Scripts that delete templates.** `templates.delete` now refuses with the pages that use the template, in a longer message. If you parse the message, match on the start, "Template [name] is used by".
- **Fields created on one-language sites** are now translatable when their type allows it (text, textarea and URL). Existing fields keep their setting. If you create fields from code with `translatable: false`, nothing changes.
- **A monorepo clone** (only when you work on Lynx itself): `packages/admin/dist` is no longer committed. Run `npm --prefix packages/admin ci` once and `composer assets` to build it. Installs from Packagist are unaffected.

## From 0.1.0 to 0.1.1

- **Run `php artisan lynx:migrate`** if you installed a module with migrations (page cache, forms, media and others) without running `migrate` afterwards. Its screens answered 500 until then.
- **Module authors:** register admin screens with `Admin::screen()` or a controller, not a closure. A closure route written on the same line as `Admin::routes()` breaks once routes are cached (see [Extending the admin](/admin/extending#a-new-screen)).
