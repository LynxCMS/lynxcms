<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Backups;

/**
 * Database backups, made by a module (lynxcms/backup, ADR-0158). Code that wants a backup before a
 * risky change asks for one through this contract and never depends on the module: when nothing
 * is bound, there are no backups to make.
 *
 * @api
 */
interface DatabaseBackups
{
    /** Whether a backup can be made: the module is set up and the database's driver is supported. */
    public function isAvailable(): bool;

    /**
     * Makes a backup now, in the calling process, and returns it once it is on every disk. It can
     * take minutes on a large database, so call it from a queued job or a command.
     *
     * @param  string  $reason  why the backup is made, shown to the site's owner
     *
     * @throws CouldNotBackUp when the backup fails; the failure is recorded and reported first
     */
    public function create(string $reason): BackupReference;

    /** The newest finished backup, or null before the first one. */
    public function latest(): ?BackupReference;

    /**
     * The finished backups whose files are kept, newest first.
     *
     * @return list<BackupReference>
     */
    public function all(): array;
}
