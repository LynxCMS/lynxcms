<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;

/*
 * A file question without its own extensions takes documents, images, spreadsheets and archives,
 * not any file a visitor sends: staff open these. extensions(['*']) still takes anything.
 */

final class ApplicationForm extends Form
{
    public function key(): string
    {
        return 'application';
    }

    public function label(): string
    {
        return 'Application';
    }

    public function fields(): array
    {
        return [
            FormField::file('cv')->label('CV'),
            FormField::file('anything')->label('Anything')->extensions(['*']),
        ];
    }
}

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(ApplicationForm::class);
});

function apply(array $data): ActionResult
{
    return lynx()->run(SubmitForm::class, ['form' => 'application', 'language' => 'default', 'data' => $data]);
}

it('takes common document types without a list of its own', function (): void {
    expect(apply(['cv' => [UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')]])->value)->not->toBeNull()
        ->and(FormField::file('cv')->allowedExtensions())->toContain('pdf', 'docx', 'jpg', 'xlsx', 'zip');
});

it('refuses programs and scripts without a list of its own', function (string $name): void {
    expect(fn (): ActionResult => apply(['cv' => [UploadedFile::fake()->create($name, 10)]]))->toThrow(ValidationException::class);
})->with(['setup.exe', 'macro.docm', 'run.sh', 'page.html', 'icon.svg']);

it('takes any file when the question says so', function (): void {
    expect(apply(['anything' => [UploadedFile::fake()->create('setup.exe', 10)]])->value)->not->toBeNull()
        ->and(FormField::file('anything')->extensions(['*'])->allowedExtensions())->toBeNull();
});

/** A file named $name holding $content, its type read from the bytes as for a real upload. */
function realUpload(string $name, string $content): UploadedFile
{
    $path = testingPath('uploads/'.$name);
    @mkdir(dirname($path), recursive: true);
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, test: true);
}

it('refuses a file whose content does not fit its name', function (string $content): void {
    expect(fn (): ActionResult => apply(['cv' => [realUpload('invoice.pdf', $content)]]))->toThrow(ValidationException::class);
})->with([
    'a Windows program' => ['MZ'.str_repeat("\0", 200)],
    'a web page' => ['<html><body>Hi</body></html>'],
    'a shell script' => ["#!/bin/sh\necho hi\n"],
]);

it('takes files whose content fits their name', function (string $name, string $content): void {
    expect(apply(['cv' => [realUpload($name, $content)]])->value)->not->toBeNull();
})->with([
    'a PDF' => ['cv.pdf', "%PDF-1.4\n%".str_repeat('a', 100)],
    'a CSV' => ['grades.csv', "name,grade\nAna,18\n"],
    'a Word file, read as a zip' => ['cv.docx', "PK\x03\x04".str_repeat("\0", 100)],
]);

it('takes any content when the question takes any file', function (): void {
    expect(apply(['anything' => [realUpload('invoice.pdf', 'MZ'.str_repeat("\0", 200))]])->value)->not->toBeNull();
});
