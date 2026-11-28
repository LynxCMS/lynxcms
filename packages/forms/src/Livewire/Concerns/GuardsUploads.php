<?php

declare(strict_types=1);

namespace LynxCms\Forms\Livewire\Concerns;

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;
use Livewire\WithFileUploads;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Definition\FormField;

/**
 * Livewire's uploads, handed out only for the form's own file questions. Livewire gives a signed
 * upload address to any component that asks, for any property, and then takes any file up to its
 * own limit into its temporary folder. A form without a file question gives none, and a file
 * question gives one only for files its types, size and count allow, as the browser describes
 * them; the files themselves are checked again when the form is sent.
 *
 * @internal
 */
trait GuardsUploads
{
    use WithFileUploads {
        _startUpload as private startLivewireUpload;
    }

    /**
     * @param  mixed  $name  the property, "files.{question}"
     * @param  mixed  $fileInfo  what the browser says about each file: name, size in bytes, type
     * @param  mixed  $isMultiple
     */
    #[Renderless]
    public function _startUpload($name, $fileInfo, $isMultiple): void
    {
        $name = Value::string($name);
        $field = $this->fileQuestion($name) ?? abort(403);
        $problem = $this->uploadProblem($field, Value::list($fileInfo));

        if ($problem !== null) {
            try {
                // Livewire's own handler tells the browser the upload ended; its generic message gives way to the field's.
                $this->_uploadErrored($name, null, $isMultiple);
            } catch (ValidationException) {
            }

            throw ValidationException::withMessages(['data.'.$field->name => $problem]);
        }

        $this->startLivewireUpload($name, $fileInfo, $isMultiple);
    }

    private function fileQuestion(string $property): ?FormField
    {
        foreach ($this->fields() as $field) {
            if ($field->type === 'file' && $property === 'files.'.$field->name) {
                return $field;
            }
        }

        return null;
    }

    /** @param  list<mixed>  $files */
    private function uploadProblem(FormField $field, array $files): ?string
    {
        $attribute = ['attribute' => $field->label->in()];
        $held = count(Value::list($this->files[$field->name] ?? []));

        if ($files === [] || $held + count($files) > $field->maxFiles) {
            return Value::string(__('validation.max.array', [...$attribute, 'max' => $field->maxFiles]));
        }

        $extensions = $field->allowedExtensions();

        foreach ($files as $file) {
            $file = Value::array($file);

            if ($extensions !== null && ! in_array(strtolower(pathinfo(Value::string($file['name'] ?? ''), PATHINFO_EXTENSION)), $extensions, true)) {
                return Value::string(__('validation.extensions', [...$attribute, 'values' => implode(', ', $extensions)]));
            }

            if (Value::int($file['size'] ?? 0) > $field->maxKb * 1024) {
                return Value::string(__('validation.max.file', [...$attribute, 'max' => $field->maxKb]));
            }
        }

        return null;
    }
}
