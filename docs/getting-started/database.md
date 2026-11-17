---
title: Choosing a Database
description: When to run a Lynx site on SQLite, when on PostgreSQL, and why MySQL is not supported.
---

# Choosing a Database

Lynx runs on SQLite and on PostgreSQL. Use SQLite for most websites. Use PostgreSQL when the site writes a lot at the same moment, runs on several servers, or needs a feature only PostgreSQL has.

Every feature of Lynx works on both, and its tests run on both.

## SQLite

SQLite keeps the whole database in one file next to your app. PHP reads that file itself, so there is no database server.

- **Most sites read far more than they write.** Visitors read pages, and editors save one now and then. SQLite serves any number of readers at once. Its own guidance says sites with fewer than 100,000 visits a day work well, and calls that figure cautious ([Appropriate uses for SQLite](https://www.sqlite.org/whentouse.html)).
- **Pages render faster.** Each query runs inside PHP, with no trip to a server. On the 2 core server in [Server capacity](/deeper/deployment#server-capacity), the corporate starter rendered 70 to 100 pages a second on SQLite and 44 to 64 on PostgreSQL.
- **There is no server to run.** Nothing to install, update or secure, and no database password in `.env`. SQLite also works on [shared hosting](/getting-started/installation#shared-hosting), where plans often offer MySQL only.
- **Development matches production.** Your machine, the tests and the live site use the same database, and a new developer needs no database setup.
- **A backup is one file.** SQLite's `.backup` command copies it safely while the site runs.

SQLite lets one write happen at a time. The others wait their turn, usually for a few milliseconds. Keep sessions in files so that page views don't write ([Creating a Laravel app](/getting-started/installation#creating-a-laravel-app)), and turn on write ahead logging when the database sits on a local disk ([Shared hosting](/getting-started/installation#shared-hosting)). A site with a few editors never reaches the limit.

## PostgreSQL

PostgreSQL is a database server. Choose it when the site needs one of these:

- **Many writes at the same moment**, such as bookings, busy forms, a members' area or a shop. PostgreSQL runs writes side by side.
- **Several web servers** behind a load balancer. They all reach one database over the network, and an SQLite file can't be shared that way safely.
- **Fast text filters on large sites.** With `LYNX_TRIGRAM=true`, Lynx adds trigram indexes to text fields, so selectors such as `title%=risk` stay fast on tens of thousands of pages ([Text operators on PostgreSQL](/basics/selectors#text-operators-on-postgresql)).
- **Extensions for your own features.** Your app's models can use PostgreSQL's extensions next to Lynx: PostGIS for maps and distances ("offices within 20 km"), and pgvector to find records by meaning with AI embeddings. Lynx itself uses neither.
- **Managed hosting**, with automatic backups, replicas and recovery to a point in time.

On PostgreSQL, persistent connections make pages render faster ([Rendering on PostgreSQL](/modules/page-cache#rendering-on-postgresql)).

## Search

The search index uses each database's full text search: FTS5 on SQLite, and a `tsvector` column with a GIN index on PostgreSQL. Both find the same pages for the same words, singular and plural forms included ([Searching text](/basics/selectors#searching-text)).

## Moving to PostgreSQL

A site can start on SQLite and move once it grows. Export the content with its schema, point `.env` at PostgreSQL, run the migrations and import the package, as [Content packages](/deeper/schema-and-content#content-packages) describes for a new environment. Your app's own tables, such as `users`, move with your usual tools.

## MySQL and MariaDB

Lynx does not run on MySQL or MariaDB. Adding a field creates a table, and Lynx runs each change inside a database transaction, so a change that fails or a [dry run](/deeper/actions-and-hooks#dry-runs) leaves nothing behind. SQLite and PostgreSQL can undo a new table inside a transaction. MySQL and MariaDB save it at once, so a dry run that adds a field would keep the table.
