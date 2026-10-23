---
title: Release Notes
description: What changed in each Lynx release.
order: 1
---

# Release Notes

Every `lynxcms/*` package shares one version number, and only classes marked `@api` are public API. The [upgrade guide](/prologue/upgrade) lists what to do between releases.

## 0.1.3

Released October 23, 2026.

- **Restoring a backup into a new database works.** `lynx:import --with-schema` used to fail on sites with a role limited to a branch of the page tree, because it applied roles before the pages they point at existed. It now imports languages, fields and templates, then the pages, then the roles. The whole import runs in one transaction, dry runs included: if any step fails, it lists every error and writes nothing.
- The `content.import` action takes `with_schema`, so code can do the same import as the command.

## 0.1.2

Released October 23, 2026.

- **Cache tab in the page editor.** Every page with a URL shows whether guests get a cached copy, and why not when they don't. Users with `page_cache.manage` also get the template's cache rule and a button to clear the page.
- **Modules can add page editor tabs** with `EditorTabs`.
- **Templates in use can't be deleted.** The template screen lists the pages that use the template, each linking to where its template is switched. The `templates.delete` refusal names those pages, so scripts and agents know what to do.
- **One-language sites see no language wording.** The admin hides the Translatable checkbox, "(default language only)" notes and similar text until a second language exists. Text, textarea and URL fields are created translatable, so adding a language later just works. `lynx()->languages->isMultilingual()` gives modules the same check.
- **Options fields explained.** The field form describes how `options` differs from `choice`, and the forms module hides `options` from the type menu with the new `FieldTypeChoices`.
- The admin's CSS and JavaScript are built when packages are split instead of being committed, so `lynxcms/admin` still ships them and needs no build step.

## 0.1.1

Released October 23, 2026.

- **Module screens work with cached routes.** After `php artisan route:cache`, the page cache, page password and sitemap screens answered 500. Modules now register screens with `Admin::screen()`, which the route cache keeps.
- **Pending module migrations are announced.** After `composer require` of a module with migrations, Composer prints which package has database changes waiting. The new `php artisan lynx:migrate` lists them, asks whether the database is backed up, and runs only Lynx's migrations.

## 0.1.0

Released October 22, 2026. The first public release: core, admin, MCP server, AI agent and the menus, media, forms, SEO, sitemap, page cache, page password and static site modules, on Packagist.
