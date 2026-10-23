<?php

declare(strict_types=1);

namespace LynxCms\Core\Migrations;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Warn;
use Throwable;

/**
 * Composer runs php artisan package:discover after every require or update, but never a
 * package's migrations. After it, this names the Lynx packages with database changes waiting.
 *
 * @internal
 */
final readonly class DiscoverWarning
{
    public function __construct(private PendingMigrations $migrations) {}

    public function handle(CommandFinished $event): void
    {
        if ($event->command !== 'package:discover') {
            return;
        }

        try {
            $pending = $this->migrations->byPackage();
        } catch (Throwable) {
            // Composer often runs without a database (CI, Docker builds): a warning must never
            // fail the install, and lynx:migrate reports the real problem later.
            return;
        }

        $warn = new Warn(new OutputStyle($event->input, $event->output));

        foreach ($pending as $package => $names) {
            $count = count($names);
            $warn->render("{$package} has {$count} database ".($count === 1 ? 'change' : 'changes').' waiting. Run php artisan lynx:migrate once the database is backed up');
        }
    }
}
