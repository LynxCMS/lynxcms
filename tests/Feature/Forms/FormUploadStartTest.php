<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Livewire\FormComponent;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

/*
 * Livewire hands out a signed upload address to a component that asks for one. A form hands it
 * out only for its own file questions, and only for files their types, sizes and counts allow,
 * so a form without one stores nothing in Livewire's temporary folder.
 */

final class NoteForm extends Form
{
    public function key(): string
    {
        return 'note';
    }

    public function label(): string
    {
        return 'Note';
    }

    public function fields(): array
    {
        return [FormField::text('message')->label('Message')];
    }
}

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    lynx()->forms()->register(NoteForm::class);
});

function formOnCourses(string $form): Testable
{
    return Livewire::test(FormComponent::class, ['form' => $form, 'pageId' => (int) lynx()->pages->get('/courses/')?->id]);
}

/** @return list<array{name: string, size: int, type: string}> */
function upload(string $name, int $kilobytes = 100): array
{
    return [['name' => $name, 'size' => $kilobytes * 1024, 'type' => 'application/octet-stream']];
}

it('gives an upload address for a file question', function (): void {
    formOnCourses('quote')->call('_startUpload', 'files.brief', upload('brief.pdf'), false)
        ->assertDispatched('upload:generatedSignedUrl');
});

it('refuses an upload to a property that is no file question', function (string $form, string $property): void {
    formOnCourses($form)->call('_startUpload', $property, upload('brief.pdf'), false)
        ->assertForbidden();
})->with([
    'a form without files' => ['note', 'files.message'],
    'a text answer' => ['quote', 'data.name'],
    'an unknown question' => ['quote', 'files.other'],
]);

it('refuses a file of a type, size or count the question does not take', function (array $files): void {
    formOnCourses('quote')->call('_startUpload', 'files.brief', $files, true)
        ->assertDispatched('upload:errored')
        ->assertNotDispatched('upload:generatedSignedUrl')
        ->assertHasErrors('data.brief');
})->with([
    'type' => [upload('setup.exe')],
    'size' => [upload('brief.pdf', 4096)],
    'count' => [[...upload('a.pdf'), ...upload('b.pdf'), ...upload('c.pdf')]],
]);
