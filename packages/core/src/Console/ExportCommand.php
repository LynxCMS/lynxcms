<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Content\PackageArchive;
use Override;

/** Exports the schema and every page as a content package (ADR-0040), as a directory or a .zip. */
final class ExportCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:export {path : Directory, or a file ending in .zip} {--without-files : Leave media files out; values keep naming them}';

    #[Override]
    protected $description = 'Export the schema and all pages as a content package';

    public function handle(ContentExporter $exporter): int
    {
        $path = (string) $this->argument('path');
        $isZip = str_ends_with($path, '.zip');
        $directory = $isZip ? sys_get_temp_dir().'/lynx-export-'.bin2hex(random_bytes(6)) : $path;

        $count = $exporter->export($directory, ! $this->option('without-files'));

        if ($isZip) {
            PackageArchive::zip($directory, $path);
            File::deleteDirectory($directory);
        }

        $this->components->info("Exported {$count} page(s) to {$path}.");

        return self::SUCCESS;
    }
}
