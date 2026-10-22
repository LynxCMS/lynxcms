<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LynxCms\Forms\Actions\DeleteSubmission;
use LynxCms\Forms\Actions\PurgeSubmissions;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Actions\UpdateSubmission;
use LynxCms\Forms\Models\Submission;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    lynx()->run(SubmitForm::class, ['form' => 'quote', 'data' => [
        'type' => 'film', 'name' => 'Ana', 'email' => 'ana@example.com', 'consent' => true,
        'brief' => [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')],
    ]]);
    $this->submission = Submission::query()->sole();
});

it('marks a submission read and keeps notes', function (): void {
    lynx()->run(UpdateSubmission::class, ['submission' => $this->submission->uuid, 'status' => 'read', 'notes' => 'Call back Monday']);

    $fresh = $this->submission->fresh();
    expect($fresh?->status)->toBe('read')->and($fresh?->notes)->toBe('Call back Monday')->and($fresh?->read_at)->not->toBeNull();
});

it('refuses unknown statuses', function (): void {
    lynx()->run(UpdateSubmission::class, ['submission' => $this->submission->uuid, 'status' => 'lost']);
})->throws(ValidationException::class);

it('changes nothing on a dry run', function (): void {
    lynx()->run(UpdateSubmission::class, ['submission' => $this->submission->uuid, 'status' => 'archived'], dryRun: true);

    expect($this->submission->fresh()?->status)->toBe('new');
});

it('deletes a submission with its files', function (): void {
    $path = $this->submission->files->first()->path;
    $result = lynx()->run(DeleteSubmission::class, ['submission' => $this->submission->uuid]);

    expect(Submission::query()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and($result->changes->isDestructive())->toBeTrue();
});

it('keeps the files when a delete is a dry run', function (): void {
    lynx()->run(DeleteSubmission::class, ['submission' => $this->submission->uuid], dryRun: true);

    expect(Submission::query()->count())->toBe(1)->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

it('purges old spam and archived submissions only', function (): void {
    $this->submission->forceFill(['status' => 'spam', 'created_at' => now()->subDays(100)])->save();
    lynx()->run(SubmitForm::class, ['form' => 'quote', 'data' => ['type' => 'film', 'name' => 'Rui', 'email' => 'rui@example.com', 'consent' => true]]);

    lynx()->run(PurgeSubmissions::class, ['older_than_days' => 30]);

    expect(Submission::query()->pluck('status')->all())->toBe(['new'])->and(Storage::disk('local')->allFiles())->toBe([]);
});
