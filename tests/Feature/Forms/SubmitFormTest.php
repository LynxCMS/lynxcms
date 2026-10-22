<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Events\FormSubmitted;
use LynxCms\Forms\Models\Submission;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
});

/** @return array<string, mixed> */
function validQuote(array $overrides = []): array
{
    return $overrides + [
        'type' => 'live',
        'cameras' => 3,
        'deliverables' => [['format' => 'Live stream', 'minutes' => 90]],
        'brief' => [UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf')],
        'name' => 'Ana Lima',
        'email' => 'ana@example.com',
        'consent' => true,
    ];
}

function submitQuote(array $data, bool $dryRun = false, string $language = 'pt'): mixed
{
    return lynx()->run(SubmitForm::class, ['form' => 'quote', 'data' => $data, 'page' => '/courses/', 'language' => $language], dryRun: $dryRun);
}

it('stores a submission with its values, files and a snapshot of the form', function (): void {
    Event::fake([FormSubmitted::class]);
    submitQuote(validQuote());

    $submission = Submission::query()->sole();
    expect($submission->form)->toBe('quote')
        ->and($submission->status)->toBe('new')
        ->and($submission->data)->toMatchArray(['type' => 'live', 'cameras' => 3, 'name' => 'Ana Lima', 'consent' => true])
        ->and($submission->data)->not->toHaveKey('brief')
        ->and($submission->page_id)->toBe(lynx()->pages->get('/courses/')?->id)
        ->and($submission->language()?->code)->toBe('pt')
        ->and($submission->fields[0]['name'])->toBe('type')
        ->and($submission->files)->toHaveCount(1)
        ->and(Storage::disk('local')->exists($submission->files->first()->path))->toBeTrue();
    Event::assertDispatched(FormSubmitted::class, fn (FormSubmitted $event): bool => $event->submissionId === $submission->id);
});

it('leaves out values of fields the answers hide', function (): void {
    submitQuote(validQuote(['type' => 'film', 'cameras' => 4]));

    expect(Submission::query()->sole()->data)->not->toHaveKey('cameras');
});

it('reports errors by field and stores nothing', function (): void {
    expect(fn (): mixed => submitQuote(validQuote(['email' => 'nope'])))->toThrow(ValidationException::class)
        ->and(Submission::query()->count())->toBe(0);

    try {
        submitQuote(validQuote(['email' => 'nope']));
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('data.email');
    }
});

it('refuses forms that do not exist', function (): void {
    lynx()->run(SubmitForm::class, ['form' => 'nope', 'data' => []]);
})->throws(ValidationException::class, 'nope');

it('writes nothing on a dry run', function (): void {
    submitQuote(validQuote(), dryRun: true);

    expect(Submission::query()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('removes stored files when the action rolls back', function (): void {
    lynx()->hook(SubmitForm::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn (): mixed => submitQuote(validQuote()))->toThrow(ActionHalted::class)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('is not an agent tool', function (): void {
    expect(SubmitForm::exposedAsTool())->toBeFalse();
});
