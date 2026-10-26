<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Content\ImportContent;
use LynxCms\Core\Console\Concerns\AsksInTerminal;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Content\BackupFiles;
use LynxCms\Core\Content\PackageArchive;
use LynxCms\Core\Support\Value;
use Override;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\suggest;

/**
 * Imports a content package (ADR-0040): pages matched by key, created or updated. With --prune in a
 * terminal, a dry run shows first how many pages would go to the trash and asks before importing.
 */
final class ImportCommand extends Command implements PromptsForMissingInput
{
    use AsksInTerminal;
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:import {path : Package directory or .zip}
        {--with-schema : Also apply the package\'s schema: languages, fields and templates first, roles after the pages}
        {--prune : Move pages that are not in the package to the trash}
        {--dry-run : Show what would change without writing}';

    #[Override]
    protected $description = 'Import a content package from lynx:export or lynx:backup';

    public function handle(): int
    {
        $path = Value::string($this->argument('path'));
        $directory = str_ends_with($path, '.zip') ? PackageArchive::unzip($path) : $path;

        try {
            return $this->importFrom($directory);
        } catch (ValidationException $exception) {
            foreach (Value::strings(Arr::flatten($exception->errors())) as $message) {
                $this->line("  <fg=red>error</> {$message}");
            }

            $this->components->error('Nothing was imported.');

            return self::FAILURE;
        } finally {
            if ($directory !== $path) {
                File::deleteDirectory($directory);
            }
        }
    }

    /** @return array<string, Closure(): string> */
    #[Override]
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['path' => fn (): string => suggest(
            label: 'Import which package?',
            options: array_map(fn (string $file): string => Str::after($file, base_path().DIRECTORY_SEPARATOR), BackupFiles::latestFirst()),
            placeholder: 'A package directory or .zip',
            required: true,
            hint: 'Backups from lynx:backup, newest first. Paths are relative to the app.',
        )];
    }

    private function importFrom(string $directory): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if (! $isDryRun && $this->option('prune') && $this->canAsk() && ! $this->confirmTrashing($this->import($directory, dryRun: true))) {
            $this->components->warn('Nothing was imported.');

            return self::FAILURE;
        }

        $result = $this->working($isDryRun ? 'Checking the package...' : 'Importing...', fn (): array => $this->import($directory, $isDryRun));

        foreach (Value::strings($result['skipped'] ?? []) as $skipped) {
            $this->components->warn("Skipped {$skipped}");
        }

        $this->components->info(($isDryRun ? 'Dry run, nothing was written: ' : 'Imported: ').$this->summary($result).'.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function import(string $directory, bool $dryRun): array
    {
        return Value::map(lynx()->run(ImportContent::class, [
            'path' => $directory,
            'prune' => (bool) $this->option('prune'),
            'with_schema' => (bool) $this->option('with-schema'),
        ], dryRun: $dryRun)->value);
    }

    /** @param  array<string, mixed>  $preview */
    private function confirmTrashing(array $preview): bool
    {
        $trashed = Value::int($preview['trashed'] ?? 0);

        if ($trashed === 0) {
            return true;
        }

        $this->box('Import preview', [
            "{$this->summary($preview)}.",
            'Pages that are not in the package move to the trash.',
        ], type: 'warning');

        return confirm("Import and move {$trashed} page(s) to the trash?", default: false);
    }

    /** @param  array<string, mixed>  $result */
    private function summary(array $result): string
    {
        return sprintf(
            '%d created, %d updated, %d unchanged, %d trashed',
            Value::int($result['created'] ?? 0),
            Value::int($result['updated'] ?? 0),
            Value::int($result['unchanged'] ?? 0),
            Value::int($result['trashed'] ?? 0),
        );
    }
}
