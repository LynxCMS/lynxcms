<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Fields\SchemaTypes;
use Override;

/** Writes the fields as @property lines of Page and Item, for editors' autocomplete (ADR-0083). */
final class IdeHelperCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:ide-helper {--path= : File, default base_path("_ide_helper_lynx.php")}';

    #[Override]
    protected $description = 'Write a stub file so editors autocomplete $page->field and $item->field';

    public function handle(SchemaTypes $types): int
    {
        $path = (string) ($this->option('path') ?: base_path('_ide_helper_lynx.php'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $types->ideHelper());
        $this->components->info("Wrote {$path}. Regenerate it after changing fields.");

        return self::SUCCESS;
    }
}
