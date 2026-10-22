<?php

declare(strict_types=1);

namespace LynxCms\Core\Indexes;

use Illuminate\Console\Command;
use Override;

/** Installs pg_trgm and gives every text field a trigram index (PostgreSQL only). */
final class TrigramCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:trigram';

    #[Override]
    protected $description = 'Add trigram indexes for contains selectors (PostgreSQL)';

    public function handle(TrigramIndexes $indexes): int
    {
        if (! $indexes->isSupported()) {
            $this->components->error('Trigram indexes need PostgreSQL.');

            return self::FAILURE;
        }

        $this->components->info('Indexed '.$indexes->install().' text field(s). Set LYNX_TRIGRAM=true so new fields get the index too.');

        return self::SUCCESS;
    }
}
