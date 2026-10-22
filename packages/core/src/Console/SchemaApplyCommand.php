<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Schema\SchemaSync;
use Override;

final class SchemaApplyCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:schema:apply {--path= : Directory, default base_path("lynx/schema")} {--force : Also delete languages, fields and templates missing from the files} {--dry-run : Show the changes without applying them}';

    #[Override]
    protected $description = 'Apply schema YAML files through the normal actions (idempotent)';

    public function handle(SchemaSync $sync): int
    {
        $path = (string) ($this->option('path') ?: base_path('lynx/schema'));
        $result = $sync->apply($path, (bool) $this->option('force'), (bool) $this->option('dry-run'));

        foreach ($result->changes as $line) {
            $this->line('  '.$line);
        }

        foreach ($result->skipped as $line) {
            $this->line('  <fg=yellow>skipped</> '.$line);
        }

        foreach ($result->errors as $line) {
            $this->line('  <fg=red>error</> '.$line);
        }

        if ($result->hasErrors()) {
            $this->components->error('Nothing was applied.');

            return self::FAILURE;
        }

        $result->isEmpty()
            ? $this->components->info('The database already matches the files.')
            : $this->components->info($result->applied ? 'Applied.' : 'Dry run: nothing was changed.');

        return self::SUCCESS;
    }
}
