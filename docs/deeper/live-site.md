---
title: Working on a Live Site
description: Develop on your machine against a site that has been in production for months, without touching its users or losing your schema changes.
---

# Working on a Live Site

Once a site is live, editors write on production every day, while you change code and the schema on your machine. Each kind of data has one home, and moves in one direction:

| What | Lives in | Reaches your machine |
| --- | --- | --- |
| Code and the schema (languages, fields, templates, roles) | Your repository | `git pull` |
| Pages, their values, images and files | Production | A [content package](/deeper/schema-and-content#content-packages) |
| Users, form submissions, your app's own tables | Production | Never, by default |
| Revisions, the activity log, drafts, the trash | Production | Never |

Schema goes up with each deploy; content comes down when you ask for it. Nothing you do locally reaches production except through a deploy.

## Your machine

Use the same database as production, PostgreSQL in this case, and the same major version. Search on PostgreSQL uses full text and trigram indexes that SQLite doesn't have, and some sorts cost differently, so a query that is fine on SQLite can behave differently live.

The first time:

```bash
git clone git@github.com:you/site.git && cd site
composer install
cp .env.example .env && php artisan key:generate   # point DB_* at your local PostgreSQL
php artisan migrate
```

Then bring the content down. On production, export it, or take the newest daily backup from `storage/app/lynx/backups` if `LYNX_BACKUPS_SCHEDULE` is on:

```bash
php artisan lynx:export storage/app/site.zip        # on production
```

Copy the zip to your machine (with `scp`, or your host's file manager), and import it with its schema:

```bash
php artisan lynx:import site.zip --with-schema
php artisan lynx:superuser                          # your own account: users are not in packages
```

The package carries the images and files of its pages, private ones included, so the site looks as it does live. Page ids are new on your machine, which is why the package keys pages by their path. On a site with a lot of media, leave the files out and let your machine download them as you need them (see [Media from production](#media-from-production)).

## Media from production

A content package with every image of a large site can weigh gigabytes. Export it without its files instead, and tell your machine where production keeps them:

```bash
php artisan lynx:export storage/app/site.zip --without-files   # on production
```

```dotenv
LYNX_MEDIA_REMOTE=https://www.example.com/storage/lynx
```

`LYNX_MEDIA_REMOTE` is the https address of production's media folder: its public disk's URL followed by `lynx`, or your CDN's address for it. The imported values keep naming production's files, and when one is missing on your machine, Lynx downloads it the moment something needs it: the browser asking for the file, or a template asking for an image size, which is made from the original. A page with many new images is slow the first time and local after that.

To fetch everything ahead, queue the downloads and let a worker fill the disk while you work:

```bash
php artisan lynx:media:pull                  # every page
php artisan lynx:media:pull --page=/courses/ # one branch
php artisan queue:work
```

Downloads happen only outside production (`APP_ENV` is not `production`), only over https, and only for stored media names, with the same size and address checks as other downloads. Private files are not downloaded, since production serves them only to people who may open their page; they come with a package that has files.

## Changing the schema

Change fields and templates in your local admin or in code, then write them to the schema files and commit them with the code that uses them:

```bash
php artisan lynx:schema:export
git add lynx/schema && git commit
```

The deploy script applies them on production after the migrations (see [Deploying](/deeper/schema-and-content#deploying)). Keep the admin's schema screens read only there (`LYNX_ADMIN_SCHEMA_EDITING=false`), so the files are the only way the schema changes and a deploy never overwrites an edit made live.

## Refreshing your content

Production moves on, and now and then you want its content again. An import with `--with-schema` also brings production's schema, which is the schema of the last deploy: a field you added on your machine leaves its template, and a label you changed goes back. Nothing is deleted, since fields and values stay, but your work looks undone. So export your schema first, and apply it again after the import:

```bash
php artisan lynx:schema:export                      # your schema, to the files
php artisan lynx:import site.zip --with-schema      # production's content and schema
php artisan lynx:schema:apply                       # your schema again, on top
```

Add `--prune` to the import to move pages that production deleted to the trash. Pages you made only on your machine stay unless you prune.

## Why not a database dump

A `pg_dump` of production brings everything: real users with their emails and password hashes, form submissions with people's messages, and your app's tables, such as bookings or orders. That is personal data on a laptop, and often more than your privacy policy allows. A content package holds only what the site publishes, which is what you need to work on templates and the schema.

A dump is still the right tool for one job: reproducing a bug in data packages don't carry, such as the activity log, revisions or your app's tables. Then anonymise it before it leaves the server, and keep it off shared machines.

## What doesn't travel

- **Page ids.** They differ between environments. Find pages by path or name in code (`lynx()->pages->get('/courses/')`), never by a number you saw on production.
- **Drafts and the trash.** Packages leave them out, so editors' unpublished work stays where they are writing it.
- **Users and roles held.** Roles and their grants come with the schema, but who holds them doesn't. `lynx:superuser` gives you an account on your machine; create others with it or invite them from the admin.
- **History.** Revisions and the activity log stay on production.

After a Lynx update, run `php artisan lynx:migrate` on your machine as on production; the [upgrade guide](/prologue/upgrade) lists what else each release needs.
