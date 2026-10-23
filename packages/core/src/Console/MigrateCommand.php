<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Migrations\PendingMigrations;
use Override;

/**
 * Runs the pending migrations of Lynx packages, after the user confirms the database is backed
 * up. The app's own migrations are left to php artisan migrate.
 */
final class MigrateCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:migrate {--force : Apply without asking about a backup}';

    #[Override]
    protected $description = 'Apply the database changes of installed Lynx packages';

    public function handle(PendingMigrations $migrations): int
    {
        $pending = $migrations->byPackage();

        if ($pending === []) {
            $this->components->info('Nothing to migrate: every Lynx package is up to date.');

            return self::SUCCESS;
        }

        foreach ($pending as $package => $names) {
            $this->line("  <options=bold>{$package}</>");
            array_map(fn (string $name) => $this->line("    {$name}"), $names);
        }

        if (! $this->option('force') && ! $this->confirm('Back up your database before these changes. Is it backed up?')) {
            $this->components->warn('Nothing changed. Back up the database, then run php artisan lynx:migrate again.');

            return self::FAILURE;
        }

        $paths = array_values(array_intersect_key($migrations->paths(), $pending));

        return $this->call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]);
    }
}
