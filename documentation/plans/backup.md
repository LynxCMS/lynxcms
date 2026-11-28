# Plan: database backups (`lynxcms/backup`)

Status: proposed, for review. ADR-0158.

## Context

Lynx has content packages (ADR-0040, `lynx:backup`): the schema and every page as a `.zip`. They are not database backups. They leave out activity, revisions, drafts, schedules, users, roles, passkeys and the app's own tables, and the docs tell owners to back up the database as well. `lynx:migrate` only asks "Is it backed up?".

Three features need a real database backup:

- **Owners** want scheduled backups they can see, keep for a while and copy off the server.
- **`lynx:migrate`** should offer to make one before applying changes, instead of asking.
- **Insights** asks before an approval runs (`documentation/plans/insights.md`, "Database backup before an approval"). That plan fixed the contract: `DatabaseBackups::create()`, events from the backup module, and Insights calling it from its own job.

## Decisions

1. **A free module, `lynxcms/backup`, on `spatie/laravel-backup`.** It dumps MySQL, PostgreSQL and SQLite through `spatie/db-dumper`, zips and optionally encrypts the archive, copies it to any filesystem disk, cleans up old backups by a retention strategy, and fires events. It has about 32 million downloads and supports Laravel 13 from 10.5.0 (released 2026-11-18). Lynx wraps it instead of writing dumps of its own. The module requires `^10.4` until 10.5.0 is two weeks old.
2. **The database only, by default.** Files are optional (`lynx-backup.include_files`, off): media can be large, and `lynx:media:pull` and the storage disk cover them better. Content packages stay as they are, for moving content between sites.
3. **A contract in `lynxcms/contracts`:** `DatabaseBackups` (`@api`) with `isAvailable(): bool`, `create(string $reason): BackupReference` (makes the backup in the calling process, meant for a queued job), `latest(): ?BackupReference` and `all(): list<BackupReference>`. `BackupReference` (`@api`, readonly) holds an id, the disk and path, the size, when it was made, the reason and who asked. The module binds the contract; nothing else depends on the module.
4. **An index table, `lynx_backups`:** id, disk, path, size, reason, actor, status (`running`, `done`, `failed`), error, timestamps. Spatie's backups are files on a disk; the table adds why and by whom a backup was made, and lets the admin list them without reading the disk. Spatie's cleanup removes files; a listener marks their rows.
5. **Events:** `BackupStarted`, `BackupCompleted` and `BackupFailed` (`@api`), each with the `BackupReference`, fired from `create()` around Spatie's run. Other modules watch them (Insights shows "Backing up the database"); they never order work by them (see the Insights plan).
6. **Actions:** `backup.create` (queues a backup and returns its reference) and `backup.delete`. Permission `backup.manage`, which only superusers have by default: a backup holds every user's password hash and two-factor secret.
7. **Schedule:** on once the module is installed, since installing it says the owner wants backups. `LYNX_BACKUP_SCHEDULE` defaults to `daily` at 02:00; empty turns it off. Cleanup runs after each backup with Spatie's default strategy (all for 7 days, then daily for 16 days, weekly for 8 weeks, monthly for 4 months).
8. **Where backups go:** the `local` disk under `backups/`, which Laravel keeps outside `public`. The docs recommend a second, off-site disk (`LYNX_BACKUP_DISKS=local,s3`). An archive password (`LYNX_BACKUP_PASSWORD`) encrypts the zip; `lynx:doctor` warns while backups go to one disk only or have no password.
9. **Admin:** Settings > Backups lists the backups with size, age, reason and who asked, shows the schedule and the disks, and has **Back up now** (queued, with the progress dot used for running insights). Superusers can download a backup after confirming their password. There is no restore button.
10. **Restore from the command line only:** `lynx:backup:restore {id}` puts the site in maintenance mode, makes a safety backup first, asks the operator to type the database's name, restores, and runs `lynx:migrate`. Restoring from a browser would let one stolen session replace the whole database. Whether to build on `stefanzweifel/laravel-backup-restore` or write the import per driver is open (question 3).
11. **`lynx:doctor` checks:** no successful backup in the last 26 hours while scheduled; the dump binary for the connection missing (`mysqldump`, `pg_dump`; SQLite needs none, see question 4); one disk only; no archive password.
12. **Integrations:** `lynx:migrate` offers "Back up the database now?" when the module is installed, and runs `create()` before migrating. Insights asks before an approval, as its plan says.

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
- Restore refuses without the typed confirmation and makes a safety backup before it changes anything (SQLite and PostgreSQL in CI).
- The doctor checks report each problem once and nothing on a healthy setup.

## Open questions

1. **Free or paid?** The plan says free: backups protect every site, and paid modules may only use `@api` code. A paid "off-site and restore" tier is possible later.
2. **On by default after installing?** The plan says yes, daily. The other choice is off until `LYNX_BACKUP_SCHEDULE` is set, like Insights.
3. **Restore:** `stefanzweifel/laravel-backup-restore` (checked against Laravel 13 and the two-week rule before use), or Lynx's own import per driver?
4. **SQLite:** Spatie's SQLite dumper needs the `sqlite3` binary. SQLite's own `VACUUM INTO` makes a consistent copy without one; a small dumper of our own for SQLite would avoid the binary on hosts without it.
5. **Files:** include the media disk in backups when asked, or leave files to the storage provider's own backups?
