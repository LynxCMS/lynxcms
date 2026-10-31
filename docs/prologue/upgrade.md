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

## From 0.1.25 to 0.1.26

- The admin's sign-in and account screens, sidebar, search box and link dialog follow the admin's language, which is the app's locale until you set one. A site with `APP_LOCALE=pt` shows them in Portuguese: set `LYNX_ADMIN_LOCALE=en` to keep them in English. See [The admin's language](/admin/#the-admin-s-language).
- If you copied admin views into `resources/views/vendor/lynx-admin`, your copies stay in English until you compare them with the new ones.

## From 0.1.24 to 0.1.25

- Selectors leave out pages whose template has no URL. Selectors that name such a template (`template=promo`), read below such a page, or name pages by `id=` still find them. A selector that found them otherwise, such as `parent=/` or `title%=launch`, needs `include=no_url`.
- A page field's `templates` now holds on every save, not only in the admin. Code that adds a page of another template to such a field, from an import, a seeder or an agent, fails with "Pick pages that use …": widen the field's `templates`, or set the template's own for that use. Pages a field already holds stay.

## From 0.1.23 to 0.1.24

- Selectors now read `has_draft` and `has_schedule` as the page's state, so a field with either name can no longer be found by a selector, and new fields can't take them. If your site has one, create a field with another name, move its values with a seeder of your own, and update the templates and selectors that use it.
- The admin's default font is Geist, for body text and headings. If you published `config/lynx-admin.php`, your copy still names Inter and monospace headings: set `appearance.font` to `geist` and `appearance.heading_font` to `sans` for the new look, or keep yours.

## From 0.1.22 to 0.1.23

- Run `php artisan lynx:phpstan`. Text fields are now typed `TranslatableValue|null` (translatable) or `SharedValue|null`, as they read at runtime, so PHPStan reports code that calls a method on a field that may be empty, such as `$page->title->value()`. Use `$page->title?->value()`, cast with `(string) $page->title` (an empty field gives `''`), or check it first.

## From 0.1.21 to 0.1.22

- A translatable text with no value in the current language's fallback chain now reads as `null`, even when another language holds a value. If your code reaches other languages through such a field, as in `$page->title->in('fr')` or `->has('fr')`, read them with `$page->getLanguageValue('fr', 'title')` instead, which works whatever the current language is. Templates that worked around the empty object, such as `(string) $page->title !== ''`, keep working and can go back to `@if ($page->title)`.

## From 0.1.19 to 0.1.20

- Run `php artisan lynx:install`. It creates the page not found, `/not-found/` with a `not-found` template, unless someone already chose a page or none. Then add `resources/views/templates/not-found.blade.php` in your site's layout ([The page not found](/basics/views#the-page-not-found)). Until the view exists, rendering it fails, the error is reported, and Laravel's own 404 answers as before.

## From 0.1.18 to 0.1.19

- `lynx:phpstan-stub` is now `lynx:phpstan`, and writes `lynx/phpstan/types.php` instead of a stub. In `phpstan.neon`, remove `lynx/phpstan/Page.stub` from `stubFiles`, include `vendor/lynxcms/core/phpstan/extension.neon`, and delete the old stub.

## From 0.1.17 to 0.1.18

- If you use the page cache with visibility rules of your own, give each rule an `audience()` (`VariesByVisitor`). A rule without one now keeps pages out of the cache, since Lynx can't tell who sees what.
- `ask(confirm: ...)`, and `lynx:ask` without `--apply`, now replay an approved plan in a new transaction instead of committing the one the agent worked in. A run also stops after two minutes (`LYNX_AI_PLAN_SECONDS`).
- Run `php artisan lynx:migrate`: invitation tokens move to their own table. Invitations already sent keep working.
- File questions in forms that list no types now take only documents, images, spreadsheets, presentations and zip files (`FormField::DEFAULT_EXTENSIONS`). If a form must take anything, give it `extensions(['*'])`, or `*` in the builder's file types.

## From 0.1.16 to 0.1.17

A security release with no database changes. Read each point before updating a live site:

- A user without a role no longer has every right while no one holds a role. If nobody on your site has a role yet, nobody can open the admin after the update: run `php artisan lynx:superuser` and give your account's email (it creates the account if there is none). Sites that already have a superuser see no change.
- Web requests with nobody signed in run actions as a visitor, who may only run actions open to everyone. Code that writes on a visitor's behalf (a webhook, a public form of your own) must say so with `lynx()->actingAs(Actor::system(), fn () => ...)`; without it, it now fails with an authorization error.
- Files and images fields no longer accept types a server or a browser runs, even if their extensions setting lists one (`php`, `html`, `svg`, `js`…). Files already stored stay where they are; check public disks for any you did not expect.
- `media.store` no longer downloads from hosts on your own network. If an importer downloads from one, add it to `downloads.allowed_hosts` in `config/lynx-media.php`.
- MCP clients on the web route need a token made with the `lynx:mcp` ability: `$user->createToken('assistant', ['lynx:mcp'])`. Tokens with every ability (`['*']`) are refused too; set `lynx.mcp.web.ability` to `''` to accept any token.
- `media.store` no longer takes an `upload` path. Send the file's contents as `base64`, with a `filename`.
- Templates that list page references now get only published pages, out of the trash, on the site. The admin still gets every page.
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
