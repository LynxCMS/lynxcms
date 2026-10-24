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
