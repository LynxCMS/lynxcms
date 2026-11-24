<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\ImportForm;

/**
 * Import form on the Forms tab (ADR-0146): picking a file, or changing the name, runs forms.import
 * as a dry run and shows what it would create; Create form then runs it and opens the new form in
 * the editor, saying which languages' texts were left out.
 *
 * @internal
 */
trait ImportsForms
{
    use WithFileUploads;

    /** The largest form export Import form takes, in KB. */
    private const int IMPORT_MAX_KB = 1024;

    /** A form export picked in Import form's file input. */
    public ?TemporaryUploadedFile $importFile = null;

    /** The new form's name, when the site has the export's. */
    public string $importName = '';

    /**
     * What the import would create: name, fields, steps and the languages left out.
     *
     * @var array{name: string, fields: int, steps: int, dropped: list<string>}|null
     */
    #[Locked]
    public ?array $importPreview = null;

    public function updatedImportFile(): void
    {
        $this->previewImport();
    }

    public function updatedImportName(): void
    {
        // A name is a key in lowercase: "Resources" is taken as "resources", and the box shows it.
        $this->importName = Str::lower(trim($this->importName));

        if ($this->importFile !== null) {
            $this->previewImport();
        }
    }

    public function previewImport(): void
    {
        $this->importPreview = null;
        $form = $this->importedForm();
        $result = $this->runImport($form, dryRun: true);
        $blocks = array_values(array_filter(Value::list($form['fields'] ?? []), fn (mixed $block): bool => Value::bool(Value::map($block)['enabled'] ?? true)));
        $types = array_map(fn (mixed $block): string => Value::string(Value::map($block)['type'] ?? ''), $blocks);
        $stepBlocks = count(array_keys($types, 'step', true));

        $this->importPreview = [
            'name' => Value::string($result['name'] ?? ''),
            'fields' => count($types) - $stepBlocks,
            'steps' => max(1, $stepBlocks + (($types[0] ?? 'step') === 'step' ? 0 : 1)),
            'dropped' => Value::strings($result['dropped_languages'] ?? []),
        ];
    }

    public function import(): void
    {
        $result = $this->runImport($this->importedForm(), dryRun: false);
        $dropped = Value::strings($result['dropped_languages'] ?? []);
        session()->flash('lynx-notify', $dropped === []
            ? ['message' => Value::string(__('lynx-forms::admin.list.imported')), 'type' => 'success']
            : ['message' => Value::string(__('lynx-forms::admin.list.imported_dropped', ['languages' => implode(', ', $dropped)])), 'type' => 'warning']);
        $this->redirectRoute('lynx.admin.pages.edit', ['page' => Value::int($result['page'] ?? 0)]);
    }

    /** @return array<array-key, mixed> the file's JSON */
    private function importedForm(): array
    {
        abort_unless(app(AdminAccess::class)->allows('forms.manage'), 403);
        $this->validate(['importFile' => ['required', 'file', 'max:'.self::IMPORT_MAX_KB]]);

        try {
            return Value::map(json_decode((string) $this->importFile?->get(), true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw ValidationException::withMessages(['importFile' => Value::string(__('lynx-forms::admin.list.import_not_json'))]);
        }
    }

    /**
     * forms.import, with its refusals on the box they are about.
     *
     * @param  array<array-key, mixed>  $form
     * @return array<array-key, mixed>
     */
    private function runImport(array $form, bool $dryRun): array
    {
        try {
            return Value::map(lynx()->run(ImportForm::class, ['form' => $form] + array_filter(['name' => trim($this->importName)]), $dryRun)->value);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([isset($e->errors()['name']) ? 'importName' : 'importFile' => $e->validator->errors()->first()]);
        }
    }
}
