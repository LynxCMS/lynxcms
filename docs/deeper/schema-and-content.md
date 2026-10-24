---
title: Schema and Content Packages
description: Keep the schema in Git and apply it on deploy, move content between sites, and back it up.
---

# Schema and Content Packages

A Lynx site's database holds two different things: the **schema** (languages, fields, templates and roles), which developers design, and the **content** (pages and their values), which editors write. They travel differently:

- The schema belongs in your repository, like migrations: you change it on your machine, commit it, and apply it on every other environment.
- Content belongs to the site that holds it. When it has to move, such as from staging to production or into a fresh install for a demo, you move it as a **content package**.

## Schema files

Export the schema as YAML, one file per language, field, template and role, in `lynx/schema`:

```bash
php artisan lynx:schema:export
```

Commit the folder. On another machine, apply it:

```bash
php artisan lynx:schema:apply --dry-run   # shows what would change
php artisan lynx:schema:apply             # applies it
```

Applying creates and updates what the files describe, through the same actions as the admin, and is safe to run again: when the database already matches, it changes nothing. It never deletes a field, template or language that is missing from the files unless you add `--force`, because deleting a field deletes its values.

### On production

On production, make the admin's schema screens read only, so the schema only changes through the files and nobody's edit is overwritten by the next deploy:

```dotenv
LYNX_ADMIN_SCHEMA_EDITING=false
```

### Deploying

A deploy script applies Lynx's database changes and the schema after installing the code:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force             # your app's migrations, and Lynx's
php artisan lynx:schema:apply           # the schema files in lynx/schema
php artisan optimize                    # cache config, routes, views and events
```

### How files match the database

Each language, field, template and role in the files carries a uuid, and applying matches items by it, so renaming a field in the admin and exporting again renames it everywhere. That makes schema files a way to keep environments **that share an origin** in step: your machine, staging and production, created from one database or one content package.

A fresh `lynx:install` creates its own default language, home template and `title` field, with uuids the files don't know. Applying files to it later lists those three as missing from the files, and `--force` would delete them. So don't build a new environment by installing and then applying files. Start it from a content package instead, which brings the schema and the pages together (see below), then keep it in step with the files from there.

## Content packages

A content package holds the schema and every page, with each page keyed by its path, so it moves between sites whose page ids differ:

```bash
php artisan lynx:export site.zip
php artisan lynx:import site.zip --with-schema --dry-run
php artisan lynx:import site.zip --with-schema
```

An import creates the pages that are missing and updates the ones that differ, and importing a site's own export changes nothing. `--prune` moves pages the package doesn't have to the trash. Drafts and the trash are left out of packages.

With the media package installed, a package also carries the images and files its pages use, in a `files` folder. An import stores them with the page they land on, and reuses a file the page already has with the same name and contents.

This is also how to start a new environment, such as a new staging server or a developer's machine. Migrate, import a package exported from an existing environment with `--with-schema`, and only then run `lynx:install` to make your user a superuser:

```bash
php artisan migrate --force
php artisan lynx:import site.zip --with-schema
php artisan lynx:install --user=ada@example.com
```

The import brings languages, fields and templates first, then the pages, then the roles, whose grants may name branches of those pages. It all succeeds or nothing is written. Running `lynx:install` afterwards finds the default language, the home page and the roles already there and adds nothing to the schema, so the new environment shares the source's uuids and schema files keep it in step. Users are not in packages, which is why the last step names one: an account of that environment, by email or id. Without `--user`, it offers to create a superuser.

## Backups

```bash
php artisan lynx:backup
```

`lynx:backup` writes a timestamped content package to `storage/app/lynx/backups`, and keeps the newest 14 (`LYNX_BACKUPS_PATH`, `LYNX_BACKUPS_KEEP`). Set `LYNX_BACKUPS_SCHEDULE` to `daily` or `hourly` to run it from the [scheduler](/getting-started/installation#the-scheduler). Restore one with `lynx:import <zip> --with-schema --prune`.

> [!WARNING]
> Content packages are not database backups. The activity log, revisions, users and your app's own tables are not in them, so back up the database as well.
