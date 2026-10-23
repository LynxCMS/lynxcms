---
title: Schema and content between environments
description: Schema sync with YAML, content packages, imports and backups.
order: 5
---

# Schema and content between environments

## Schema sync

Languages, fields, templates and roles export as one YAML file each. Commit them and apply them on other machines.

```bash
php artisan lynx:schema:export            # writes lynx/schema
php artisan lynx:schema:apply --dry-run   # shows the changes
php artisan lynx:schema:apply             # applies them
php artisan lynx:schema:apply --force     # also deletes items missing from the files
```

Set `lynx-admin.schema_editing` to `false` (`LYNX_ADMIN_SCHEMA_EDITING=false`) on production to make the admin's schema screens read only, so schema changes only come from the files.

## Content packages

A content package holds the schema and every page, keyed by page path (`/courses/alpha/`), so it moves between sites.

```bash
php artisan lynx:export site.zip
php artisan lynx:import site.zip --with-schema --dry-run
php artisan lynx:import site.zip --with-schema
php artisan lynx:import site.zip --prune      # trashes pages missing from the package
```

Imports create missing pages and update pages that differ. Importing a site's own export changes nothing. Drafts and the trash are left out.

With the media package installed, packages carry the images and files pages use, in a `files` folder. An import stores them in the folder of the page they land on, and reuses a file the page already has with the same name and contents.

## Backups

```bash
php artisan lynx:backup
```

Writes a timestamped package to `storage/app/lynx/backups` and keeps the newest 14 (`LYNX_BACKUPS_PATH`, `LYNX_BACKUPS_KEEP`). Set `LYNX_BACKUPS_SCHEDULE` to `daily` or `hourly` to run it from the scheduler. Restore with `lynx:import <zip> --with-schema --prune`.

> [!WARNING]
> Content packages are not database backups. Activity, revisions, users and your app's tables are not in them, so back up the database as well.
