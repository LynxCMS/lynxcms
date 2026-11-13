<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Console\Concerns\AsksInTerminal;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Content\PackageArchive;
use Override;

use function Laravel\Prompts\text;

/**
 * Exports the schema and every page as a content package (ADR-0040), as a directory or a .zip.
 * Media files stay out unless --with-files asks for them: copying a large site's media can take
 * long and weigh gigabytes, and machines can download them from production instead (ADR-0082).
 */
final class ExportCommand extends Command implements PromptsForMissingInput
{
    use AsksInTerminal;
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:export {path : Directory, or a file ending in .zip} {--with-files : Copy the media files the pages use into the package}';

    #[Override]
    protected $description = 'Export the schema and all pages as a content package';

    public function handle(ContentExporter $exporter): int
    {
        $path = (string) $this->argument('path');
        $withFiles = (bool) $this->option('with-files');

        $count = $this->working('Exporting...', fn (): int => $this->write($exporter, $path, $withFiles));

        $this->components->info("Exported {$count} page(s) to {$path}.");

        if (! $withFiles) {
            $this->line('  Media files were left out; the pages still name them. Add --with-files to include them.');
        }

        return self::SUCCESS;
    }

    /** Writes the package to a directory, or to a temporary one that is then zipped. */
    private function write(ContentExporter $exporter, string $path, bool $withFiles): int
    {
        $isZip = str_ends_with($path, '.zip');
        $directory = $isZip ? sys_get_temp_dir().'/lynx-export-'.bin2hex(random_bytes(6)) : $path;
        $count = $exporter->export($directory, $withFiles);

        if ($isZip) {
            PackageArchive::zip($directory, $path);
            File::deleteDirectory($directory);
        }

        return $count;
    }

    /** @return array<string, Closure(): string> */
    #[Override]
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['path' => fn (): string => text('Export to which directory or .zip file?', placeholder: 'storage/app/lynx/site.zip', required: true)];
    }
}
