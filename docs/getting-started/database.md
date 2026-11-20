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

SQLite lets one write happen at a time. The others wait their turn, usually for a few milliseconds. Keep sessions in files so that page views don't write ([Creating a Laravel app](/getting-started/installation#creating-a-laravel-app)), and turn on write ahead logging when the database sits on a local disk ([Write ahead logging](#write-ahead-logging)). A site with a few editors never reaches the limit.

### Write ahead logging

By default SQLite writes a change straight into the database file and keeps a copy of the old pages in a journal, so it can undo the change if something fails. While a write is saved, nobody can read. In write ahead logging (WAL) mode, SQLite does the opposite: it appends changes to a separate `-wal` file and copies them into the database later, in a step called a checkpoint. Readers keep reading the database while the write happens.

Turn it on in the `sqlite` connection of `config/database.php`:

```php
'sqlite' => [
    // ...
    'journal_mode' => 'wal',
    'busy_timeout' => 5000,
    'synchronous' => 'normal',
],
```

`busy_timeout` makes a write wait up to 5 seconds for its turn instead of failing with "database is locked". The mode is stored in the database file, so it stays on once set.

It helps when:

- **Editors save while visitors read.** A save no longer pauses page views, and page views no longer delay a save. This matters most when the page cache is off or many pages expire at once.
- **Background work writes.** Queue jobs, scheduled imports, form submissions and search indexing write while the site serves pages.
- **Writes come often.** Each save is faster, because SQLite appends to one file instead of writing to two. With `synchronous` set to `normal`, it also syncs to disk less often, and a power cut can lose only the last few saves, never corrupt the file.

Pros:

- Readers and the writer don't block each other.
- Faster writes, with fewer disk syncs.
- Fewer "database is locked" errors under load.

Cons:

- **Still one writer at a time.** WAL lets reads run during a write. It does not let two writes run together. Many writes at the same moment still call for PostgreSQL.
- **Local disks only.** WAL shares memory between processes through a `-shm` file, which doesn't work on network storage such as NFS, SMB or some shared hosting setups. There, leave the defaults, or the file can be corrupted.
- **Two more files.** `database.sqlite-wal` and `database.sqlite-shm` sit next to the database. The web server user needs write access to the folder, not only to the file, even to read.
- **Copying the file is no longer a backup.** Recent changes may still be in the `-wal` file. Back up with SQLite's `.backup` command or `VACUUM INTO`, never with `cp` alone, and don't copy or replace the database file during a deploy while the site runs.
- **The `-wal` file can grow.** A checkpoint can't finish while a long read is still running, so on a busy site with long reads the file grows until there is a quiet moment. SQLite resets it on its own; most sites never notice.
- **Slightly slower reads in rare cases.** Each read also checks the `-wal` file. With a small file the difference can't be measured.

For a site on one server with a local disk, the benefits outweigh the costs. On network storage, or when the database file is copied around by deploy or backup scripts that you can't change, keep the default journal.

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
