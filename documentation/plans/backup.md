# Plan: database backups (`lynxcms/backup`)

Status: accepted. ADR-0158.

## Context

Lynx has content packages (ADR-0040, `lynx:backup`): the schema and every page as a `.zip`. They are not database backups. They leave out activity, revisions, drafts, schedules, users, roles, passkeys and the app's own tables, and the docs tell owners to back up the database as well. `lynx:migrate` only asks "Is it backed up?".

Three features need a real database backup:

- **Owners** want scheduled backups they can see, keep for a while and copy off the server.
- **`lynx:migrate`** should offer to make one before applying changes, instead of asking.
- **Insights** asks before an approval runs (`documentation/plans/insights.md`, "Database backup before an approval"). That plan fixed the contract: `DatabaseBackups::create()`, events from the backup module, and Insights calling it from its own job.

## Decisions

1. **A free module, `lynxcms/backup`, on `spatie/laravel-backup`.** It dumps MySQL, PostgreSQL and SQLite through `spatie/db-dumper`, zips and optionally encrypts the archive, copies it to any filesystem disk, cleans up old backups by a retention strategy, and fires events. It has about 32 million downloads and supports Laravel 13 from 10.5.0 (released 2026-11-18). Lynx wraps it instead of writing dumps of its own. The module requires `^10.4` until 10.5.0 is two weeks old.
2. **SQLite has two formats.** `copy` (the default) makes the backup with `VACUUM INTO` through PDO: a consistent, compacted database file, made without a binary and without blocking writes in WAL mode. The owner can download it and open it in any SQLite tool. `dump` uses Spatie's dumper, which needs the `sqlite3` binary and writes SQL text. Settings > Backups offers the choice when `sqlite3` is found, and shows `copy` alone otherwise; `LYNX_BACKUP_SQLITE_FORMAT` sets it in code. The `copy` dumper is registered with `DbDumperFactory::extend('sqlite', ...)`, so Spatie still zips, encrypts, copies and cleans up as for any other driver.
3. **The database by default, files on request.** Settings > Backups has an "Include files" switch (`LYNX_BACKUP_INCLUDE_FILES`, off), which adds the media disk and `storage/app` (backups themselves left out) to the archive. Media can be large, so the switch shows the size of the last archive with and without files. Files on a remote disk (S3, R2) are left out with a note: that provider keeps its own copies. Content packages stay as they are, for moving content between sites.
4. **A contract in `lynxcms/contracts`:** `DatabaseBackups` (`@api`) with `isAvailable(): bool`, `create(string $reason): BackupReference` (makes the backup in the calling process, meant for a queued job), `latest(): ?BackupReference` and `all(): list<BackupReference>`. `BackupReference` (`@api`, readonly) holds an id, the disk and path, the size, when it was made, the reason and who asked. The module binds the contract; nothing else depends on the module.
5. **An index table, `lynx_backups`:** id, disk, path, size, reason, actor, status (`running`, `done`, `failed`), error, timestamps. Spatie's backups are files on a disk; the table adds why and by whom a backup was made, and lets the admin list them without reading the disk. Spatie's cleanup removes files; a listener marks their rows.
6. **Events:** `BackupStarted`, `BackupCompleted` and `BackupFailed` (`@api`), each with the `BackupReference`, fired from `create()` around Spatie's run. Other modules watch them (Insights shows "Backing up the database"); they never order work by them (see the Insights plan).
7. **Actions:** `backup.create` (queues a backup and returns its reference) and `backup.delete`. Permission `backup.manage`, which only superusers have by default: a backup holds every user's password hash and two-factor secret.
8. **Schedule:** on once the module is installed, since installing it says the owner wants backups. `LYNX_BACKUP_SCHEDULE` defaults to `daily` at 02:00; empty turns it off. Cleanup runs after each backup with Spatie's default strategy (all for 7 days, then daily for 16 days, weekly for 8 weeks, monthly for 4 months).
9. **Where backups go:** the `local` disk under `backups/`, which Laravel keeps outside `public`. The docs recommend a second, off-site disk (`LYNX_BACKUP_DISKS=local,s3`). An archive password (`LYNX_BACKUP_PASSWORD`) encrypts the zip; `lynx:doctor` warns while backups go to one disk only or have no password.
10. **Admin:** Settings > Backups lists the backups with size, age, reason and who asked, shows the schedule and the disks, and has **Back up now** (queued, with the progress dot used for running insights). Superusers can download a backup after confirming their password. There is no restore button.
11. **Restore from the command line only:** `lynx:backup:restore {id}` puts the site in maintenance mode, makes a safety backup first, asks the operator to type the database's name, restores, and runs `lynx:migrate`. Restoring from a browser would let one stolen session replace the whole database. It builds on `stefanzweifel/laravel-backup-restore` (`^2.1`, Laravel 13, released 2026-10-24), which reads Spatie's archives, decrypts them and imports MySQL and PostgreSQL with their clients and an SQLite `dump` through PDO. An SQLite `copy` is restored by Lynx: the archive's database file replaces the live one (after the safety backup), and the `-wal` and `-shm` files are removed first. The command reads the format from the archive, so a site can switch formats and still restore older backups. Restoring files from an archive that has them is a separate `--files` option.
12. **`lynx:doctor` checks:** no successful backup in the last 26 hours while scheduled; the dump binary for the connection missing (`mysqldump`, `pg_dump`; and `sqlite3` when SQLite uses `dump`); one disk only; no archive password.
13. **Integrations:** `lynx:migrate` offers "Back up the database now?" when the module is installed, and runs `create()` before migrating. Insights asks before an approval, as its plan says.

## Pull requests

1. The contract (`DatabaseBackups`, `BackupReference`, the events) in `lynxcms/contracts`, the module with `create()`, the table, the actions, the command (`lynx:backup:database`), the schedule and cleanup. ADR-0158. Needs `LynxCMS/backup` created and added to the split token first.
2. The admin screen and the doctor checks.
3. Restore from the command line.
4. `lynx:migrate` and Insights use it.
5. Docs: a page under Modules and the upgrade notes.

## Tests to write first

- `create()` writes a zip with a dump on the configured disk, records a `done` row with its size and reason, and fires `BackupStarted` then `BackupCompleted`; a failing dump records `failed` with its error and fires `BackupFailed`.
- `backup.create` refuses users without `backup.manage`; superusers and the system may.
- The schedule exists by default and not when `LYNX_BACKUP_SCHEDULE` is empty; cleanup marks the rows of removed files.
- An SQLite `copy` backup opens as a database with the same rows while another connection writes, needs no binary, and restores by replacing the file; a `dump` backup runs only when `sqlite3` is found.
- Restore refuses without the typed confirmation and makes a safety backup before it changes anything (SQLite and PostgreSQL in CI).
- The doctor checks report each problem once and nothing on a healthy setup.

## Answered questions

1. **Free.** A paid "off-site and restore" tier stays possible later.
2. **On by default after installing**, daily at 02:00.
3. **Restore** builds on `stefanzweifel/laravel-backup-restore`.
4. **SQLite** backs up with `VACUUM INTO` by default; owners with `sqlite3` can choose a SQL dump (see below).
5. **Files** are included when the owner turns them on.

## SQLite: `sqlite3 .dump` and `VACUUM INTO`

Spatie's SQLite dumper runs `sqlite3 --bail` with `BEGIN IMMEDIATE; .dump` and writes SQL text.

- For: the same kind of dump as MySQL and PostgreSQL (SQL text that compresses well and can be read or diffed), and the restore package imports it through PDO with no binary. `BEGIN IMMEDIATE` blocks writers while it dumps, so the dump is consistent.
- Against: hosts without the `sqlite3` binary (slim Docker images, some shared hosting) can't back up. Writers wait for the whole dump, which is seconds on a small site.

`VACUUM INTO 'file'` runs through PDO and writes a compacted copy of the database file.

- For: no binary, a consistent copy from one read transaction, writers keep working in WAL mode, and restoring is copying the file back.
- Against: the archive holds a database file instead of SQL, so the restore package can't import it, and Lynx would need its own dumper (`DbDumperFactory::extend('sqlite', ...)`) and its own SQLite restore. The copy holds every byte of the database, deleted rows excluded, and can't be read without SQLite.

Lynx makes `copy` the default: it works on every host and the backup is a database the owner can open. Where `sqlite3` is installed, owners can choose `dump` for SQL text.
