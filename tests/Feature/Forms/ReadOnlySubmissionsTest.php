<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Actions\UpdateSubmission;
use LynxCms\Forms\Actions\UpdateSubmissions;
use LynxCms\Forms\Admin\Livewire\Inbox;
use LynxCms\Forms\Admin\Livewire\SubmissionView;
use LynxCms\Forms\Models\Submission;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

/*
 * forms.view reads submissions and marks them read or new. Spam and archived submissions are
 * purged once they are old enough, so marking them, like writing the team's notes, needs
 * forms.manage.
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    lynx()->run(SubmitForm::class, ['form' => 'quote', 'data' => [
        'type' => 'film', 'name' => 'Ana', 'email' => 'ana@example.com', 'consent' => true,
        'brief' => [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')],
    ]]);
    $this->submission = Submission::query()->sole();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'reader', 'grants' => [['permission' => 'admin.access'], ['permission' => 'forms.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['reader']]);
});

function asReader(Closure $callback): mixed
{
    return lynx()->actingAs(Actor::user(2), $callback);
}

it('lets a reader mark a submission read or new', function (string $status): void {
    asReader(fn (): ActionResult => lynx()->run(UpdateSubmission::class, ['submission' => $this->submission->uuid, 'status' => $status]));

    expect($this->submission->fresh()?->status)->toBe($status);
})->with(['read', 'new']);

it('refuses a reader marking a submission spam or archived', function (string $status): void {
    expect(fn (): mixed => asReader(fn (): ActionResult => lynx()->run(UpdateSubmission::class, ['submission' => $this->submission->uuid, 'status' => $status])))
        ->toThrow(AuthorizationException::class);
    expect(fn (): mixed => asReader(fn (): ActionResult => lynx()->run(UpdateSubmissions::class, ['submissions' => [$this->submission->uuid], 'status' => $status])))
        ->toThrow(AuthorizationException::class);

    expect($this->submission->fresh()?->status)->toBe('new');
})->with(['spam', 'archived']);

it('refuses a reader writing notes', function (): void {
    expect(fn (): mixed => asReader(fn (): ActionResult => lynx()->run(UpdateSubmission::class, ['submission' => $this->submission->uuid, 'notes' => 'Ignore'])))
        ->toThrow(AuthorizationException::class);
});

it('offers a reader only Mark read and Mark new in the admin', function (): void {
    $this->actingAs(new GenericUser(['id' => 2]));

    Livewire::test(SubmissionView::class, ['uuid' => $this->submission->uuid])
        ->assertDontSeeHtml('setStatus(\'spam\')')
        ->assertDontSeeHtml('setStatus(\'archived\')')
        ->assertDontSeeHtml('id="submission-notes"');
    Livewire::test(Inbox::class)->call('togglePage')
        ->assertSeeHtml('markSelected(\'read\')')
        ->assertDontSeeHtml('markSelected(\'spam\')')
        ->assertDontSeeHtml('markSelected(\'archived\')');
});
