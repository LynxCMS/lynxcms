<?php

declare(strict_types=1);

namespace LynxCms\Forms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use JsonException;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\ImportForm;
use Override;

/**
 * Creates a form from a form export (forms.import, ADR-0146): unpublished and without recipients.
 *
 * @internal
 */
final class ImportFormCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:forms:import
        {path : The JSON file lynx:forms:export wrote}
        {--name= : The new form\'s name, when the site has the export\'s}
        {--dry-run : Check the file, and change nothing}';

    #[Override]
    protected $description = 'Create a form from a form export, unpublished and without recipients';

    public function handle(): int
    {
        $path = Value::string($this->argument('path'));

        try {
            $form = json_decode(File::exists($path) ? (string) File::get($path) : '', true, 512, JSON_THROW_ON_ERROR);
            $input = ['form' => $form] + array_filter(['name' => Value::nullableString($this->option('name'))]);
            $result = Value::map(lynx()->run(ImportForm::class, $input, (bool) $this->option('dry-run'))->value);
        } catch (JsonException) {
            $this->components->error("{$path} is not a form export: it can't be read as JSON.");

            return self::FAILURE;
        } catch (ValidationException $e) {
            array_map(fn (string $message) => $this->components->error($message), Value::strings($e->validator->errors()->all()));

            return self::FAILURE;
        }

        $this->report($result);

        return self::SUCCESS;
    }

    /** @param  array<array-key, mixed>  $result */
    private function report(array $result): void
    {
        $name = Value::string($result['name'] ?? $this->option('name'));
        $this->components->info($this->option('dry-run')
            ? "Dry run: form [{$name}] can be imported, and nothing was changed."
            : "Imported form [{$name}], unpublished. Add its recipients in the admin, then publish it.");
        $dropped = Value::strings($result['dropped_languages'] ?? []);

        if ($dropped !== []) {
            $this->components->warn('Texts in '.implode(', ', $dropped).' were left out: this site has no such language.');
        }
    }
}
