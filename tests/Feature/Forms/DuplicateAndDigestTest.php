<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Actions\DuplicateForm;
use LynxCms\Forms\Actions\SendDigest;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Admin\Livewire\FormList;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\Notifications\SubmissionDigest;
use LynxCms\Forms\Notifications\SubmissionReceived;
use LynxCms\Forms\Transfer\FormExporter;

/*
 * Duplicate copies a builder form within the site, with the settings an export leaves out. A form
 * with a daily summary is mailed once a day (forms.send_digest) instead of once per submission.
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

/** @param  array<string, mixed>  $values */
function digestForm(string $name = 'contact', array $values = []): Page
{
    $page = lynx()->run(CreateForm::class, ['name' => $name, 'label' => 'Contact'])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $page->id, 'status' => [], 'values' => [
        FormSchema::FIELDS => [['type' => 'text', 'depth' => 0, 'values' => ['form_label' => ['default' => 'Your name'], 'form_name' => 'name']]],
        FormSchema::RECIPIENTS => 'office@example.com',
        ...$values,
    ]]);

    return lynx()->pages->get((int) $page->id) ?? throw new RuntimeException('No form page.');
}

it('copies a form with its fields and its site settings, unpublished, under a free name', function (): void {
    digestForm(values: [FormSchema::RETENTION => 90, FormSchema::DIGEST => true]);

    $first = lynx()->run(DuplicateForm::class, ['form' => 'contact'])->value;
    $second = lynx()->run(DuplicateForm::class, ['form' => 'contact'])->value;
    $copy = lynx()->pages->get($first['page']);

    expect($first['name'])->toBe('contact-copy')->and($second['name'])->toBe('contact-copy-2')
        ->and($copy?->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue()
        ->and($copy?->getUnformatted(FormSchema::RECIPIENTS))->toBe('office@example.com')
        ->and($copy?->getUnformatted(FormSchema::RETENTION))->toBe(90)
        ->and(app(FormExporter::class)->export('contact-copy')['fields'])->toBe(app(FormExporter::class)->export('contact')['fields']);
});

it('refuses to copy a form the site lacks', function (): void {
    lynx()->run(DuplicateForm::class, ['form' => 'missing']);
})->throws(ValidationException::class);

it('duplicates from the forms list and opens the copy', function (): void {
    digestForm();

    Livewire::test(FormList::class)->assertSeeHtml('data-form-duplicate="contact"')->call('duplicate', 'contact')->assertRedirect();

    expect(lynx()->pages->findOne('template=form, name=contact-copy, include=all'))->not->toBeNull();
});

it('mails a form with a daily summary once a day, not for each submission', function (): void {
    Notification::fake();
    digestForm(values: [FormSchema::DIGEST => true]);

    lynx()->run(SubmitForm::class, ['form' => 'contact', 'data' => ['name' => 'Ana Lima']]);
    lynx()->run(SubmitForm::class, ['form' => 'contact', 'data' => ['name' => 'Rui Costa']]);
    Notification::assertNothingSent();

    expect(lynx()->run(SendDigest::class)->value)->toBe(1);
    Notification::assertSentOnDemand(SubmissionDigest::class, fn (SubmissionDigest $digest, array $channels, object $notifiable): bool => $digest->count === 2 && $digest->summaries === ['Rui Costa', 'Ana Lima'] && $notifiable->routes['mail'] === 'office@example.com');
});

it('lists each submission in one summary only, and leaves spam out', function (): void {
    Notification::fake();
    digestForm(values: [FormSchema::DIGEST => true]);
    lynx()->run(SubmitForm::class, ['form' => 'contact', 'data' => ['name' => 'Ana Lima']]);
    lynx()->run(SendDigest::class);

    $this->travel(1)->hours();
    Submission::query()->create(['uuid' => (string) Str::uuid(), 'form' => 'contact', 'data' => ['name' => 'Bot'], 'fields' => [], 'status' => 'spam']);

    expect(lynx()->run(SendDigest::class)->value)->toBe(0);
    Notification::assertSentOnDemandTimes(SubmissionDigest::class, 1);
});

it('still mails each submission of a form without a daily summary', function (): void {
    Notification::fake();
    digestForm();

    lynx()->run(SubmitForm::class, ['form' => 'contact', 'data' => ['name' => 'Ana Lima']]);

    Notification::assertSentOnDemand(SubmissionReceived::class);
    expect(lynx()->run(SendDigest::class)->value)->toBe(0);
});
