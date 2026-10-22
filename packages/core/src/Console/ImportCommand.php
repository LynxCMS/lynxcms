<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Content\ImportContent;
use LynxCms\Core\Content\ContentPackage;
use LynxCms\Core\Content\PackageArchive;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Support\Value;
use Override;

/** Imports a content package (ADR-0040): pages matched by key, created or updated. */
final class ImportCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:import {path : Package directory or .zip}
        {--with-schema : Apply the package\'s schema first (as lynx:schema:apply)}
        {--prune : Move pages that are not in the package to the trash}
        {--dry-run : Show what would change without writing}';

    #[Override]
    protected $description = 'Import a content package from lynx:export or lynx:backup';

    public function handle(SchemaSync $schema): int
    {
        $path = (string) $this->argument('path');
        $directory = str_ends_with($path, '.zip') ? PackageArchive::unzip($path) : $path;
        $isDryRun = (bool) $this->option('dry-run');

        try {
            $package = ContentPackage::open($directory);

            if ($this->option('with-schema')) {
                $schema->apply($package->schemaDirectory(), dryRun: $isDryRun);
            }

            $result = Value::map(lynx()->run(ImportContent::class, ['path' => $directory, 'prune' => (bool) $this->option('prune')], dryRun: $isDryRun)->value);
        } catch (ValidationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if ($directory !== $path) {
                File::deleteDirectory($directory);
            }
        }

        foreach (Value::strings($result['skipped'] ?? []) as $skipped) {
            $this->components->warn('Skipped '.$skipped);
        }

        $summary = sprintf('%d created, %d updated, %d unchanged, %d trashed', Value::int($result['created'] ?? 0), Value::int($result['updated'] ?? 0), Value::int($result['unchanged'] ?? 0), Value::int($result['trashed'] ?? 0));
        $this->components->info(($isDryRun ? 'Dry run, nothing was written: ' : 'Imported: ').$summary.'.');

        return self::SUCCESS;
    }
}
