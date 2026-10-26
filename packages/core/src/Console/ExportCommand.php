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

/** Exports the schema and every page as a content package (ADR-0040), as a directory or a .zip. */
final class ExportCommand extends Command implements PromptsForMissingInput
{
    use AsksInTerminal;
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:export {path : Directory, or a file ending in .zip} {--without-files : Leave media files out; values keep naming them}';

    #[Override]
    protected $description = 'Export the schema and all pages as a content package';

    public function handle(ContentExporter $exporter): int
    {
        $path = (string) $this->argument('path');
        $isZip = str_ends_with($path, '.zip');
        $directory = $isZip ? sys_get_temp_dir().'/lynx-export-'.bin2hex(random_bytes(6)) : $path;

        $count = $this->working('Exporting...', function () use ($exporter, $directory, $path, $isZip): int {
            $count = $exporter->export($directory, ! $this->option('without-files'));

            if ($isZip) {
                PackageArchive::zip($directory, $path);
                File::deleteDirectory($directory);
            }

            return $count;
        });

        $this->components->info("Exported {$count} page(s) to {$path}.");

        return self::SUCCESS;
    }

    /** @return array<string, Closure(): string> */
    #[Override]
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['path' => fn (): string => text('Export to which directory or .zip file?', placeholder: 'storage/app/lynx/site.zip', required: true)];
    }
}
