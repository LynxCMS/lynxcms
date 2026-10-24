<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Fields\SchemaTypes;
use Override;

/** Writes the field types Lynx's PHPStan extension reads (ADR-0083). */
final class PhpStanCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:phpstan {--path= : File, default base_path("lynx/phpstan/types.php")}';

    #[Override]
    protected $description = 'Write the field types PHPStan reads, so $page->field and $item->field are checked';

    public function handle(SchemaTypes $types): int
    {
        $path = (string) ($this->option('path') ?: base_path('lynx/phpstan/types.php'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $types->render());
        $this->components->info("Wrote {$path}. Include vendor/lynxcms/core/phpstan/extension.neon in phpstan.neon.");

        return self::SUCCESS;
    }
}
