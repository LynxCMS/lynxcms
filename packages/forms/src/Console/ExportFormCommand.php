<?php

declare(strict_types=1);

namespace LynxCms\Forms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Transfer\FormExporter;
use LynxCms\Forms\Transfer\FormTransferError;
use Override;

/**
 * Writes a builder form's structure as JSON (ADR-0146), to a file or to the terminal, for
 * lynx:forms:import on another site.
 *
 * @internal
 */
final class ExportFormCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:forms:export
        {form : The form\'s name}
        {path? : The file to write; without one, the JSON is printed}';

    #[Override]
    protected $description = 'Export a form built in the admin as JSON: its title, messages and fields, without recipients';

    public function handle(FormExporter $exporter): int
    {
        try {
            $json = $exporter->toJson(Value::string($this->argument('form')));
        } catch (FormTransferError $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $path = Value::nullableString($this->argument('path'));

        if ($path === null) {
            $this->output->write($json);

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $json);
        $this->components->info("Wrote {$path}. Import it on another site with php artisan lynx:forms:import {$path}.");

        return self::SUCCESS;
    }
}
