<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Schema\SchemaSync;
use Override;

final class SchemaExportCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:schema:export {--path= : Directory, default base_path("lynx/schema")}';

    #[Override]
    protected $description = 'Export languages, fields and templates as YAML files (commit them to git)';

    public function handle(SchemaSync $sync): int
    {
        $path = (string) ($this->option('path') ?: base_path('lynx/schema'));
        $count = $sync->export($path);
        $this->components->info("Exported {$count} file(s) to {$path}.");

        return self::SUCCESS;
    }
}
