<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Laravel\Prompts\Elements\Element;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Migrations\PendingMigrations;
use LynxCms\Core\Support\Tables;
use Override;

use function Laravel\Prompts\confirm;

/**
 * Runs the pending migrations of Lynx packages, after the user confirms the database is backed
 * up, then installs Lynx when it is not installed yet (no default language), so a new site has
 * its language, home page and roles. The app's own migrations are left to php artisan migrate.
 *
 * @internal
 */
final class MigrateCommand extends Command
{
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:migrate {--force : Apply without asking about a backup}';

    #[Override]
    protected $description = 'Apply the database changes of installed Lynx packages';

    public function handle(PendingMigrations $migrations): int
    {
        $pending = $migrations->byPackage();

        if ($pending === []) {
            $this->components->info('Nothing to migrate: every Lynx package is up to date.');

            return $this->installIfNeeded(self::SUCCESS);
        }

        $this->showPending($pending);

        if (! $this->option('force') && ! confirm('Back up your database before these changes. Is it backed up?', default: false)) {
            $this->components->warn('Nothing changed. Back up the database, then run php artisan lynx:migrate again.');

            return self::FAILURE;
        }

        $paths = array_values(array_intersect_key($migrations->paths(), $pending));

        return $this->installIfNeeded($this->call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]));
    }

    /** A site whose tables exist but that has no default language is installed now; installed sites are left as they are. */
    private function installIfNeeded(int $status): int
    {
        $languages = $this->laravel->make(LanguageRegistry::class);
        $languages->flush();

        if ($status !== self::SUCCESS || ! Schema::hasTable(Tables::LANGUAGES) || $languages->hasDefault()) {
            return $status;
        }

        return $this->call('lynx:install', ['--no-interaction' => true]);
    }

    /** @param  array<string, list<string>>  $pending */
    private function showPending(array $pending): void
    {
        $content = [];

        foreach ($pending as $package => $names) {
            $content[] = Element::heading($package);
            $content[] = Element::bulletedList($names);
        }

        $this->box('Pending database changes', $content, type: 'warning');
    }
}
