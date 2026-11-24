<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\Page;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Actions\PurgeSubmissions;
use LynxCms\Forms\Admin\Livewire\PersonalDataSearch;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\PersonalData;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

/*
 * One person's data across forms (ADR-0147): every submission where their email address is a whole
 * answer, exported as JSON or deleted; and retention per form, which the daily purge applies to
 * submissions in any status.
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

/** @param  array<string, mixed>  $data */
function personalRow(string $form, array $data, string $status = 'new', int $daysAgo = 1): Submission
{
    return Submission::query()->create([
        'uuid' => (string) Str::uuid(), 'form' => $form, 'data' => $data,
        'fields' => [['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'name', 'label' => 'Name', 'type' => 'text']],
        'status' => $status, 'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo),
    ]);
}

it('finds an address only where it is a whole answer, in any case, form and status', function (): void {
    personalRow('quote', ['email' => 'Ana_Lima@Example.com', 'name' => 'Ana']);
    personalRow('contact', ['email' => 'ana_lima@example.com'], status: 'spam');
    personalRow('quote', ['email' => 'joana_lima@example.com']);
    personalRow('quote', ['email' => 'anaXlima@example.com']);

    expect(app(PersonalData::class)->filter('ana_lima@example.com')->query()->count())->toBe(2);
});

it('exports what the forms hold about a person', function (): void {
    personalRow('quote', ['email' => 'ana@example.com', 'name' => 'Ana Lima']);

    $export = app(PersonalData::class)->export('ana@example.com');

    expect($export['submissions'])->toHaveCount(1)
        ->and($export['submissions'][0])->toMatchArray(['form' => 'quote', 'status' => 'new'])
        ->and($export['submissions'][0]['answers'])->toContain(['question' => 'Name', 'answer' => 'Ana Lima']);
});

it('lists, downloads and deletes a person\'s submissions on the Personal data tab', function (): void {
    personalRow('quote', ['email' => 'ana@example.com']);
    personalRow('contact', ['email' => 'ana@example.com'], status: 'archived');
    personalRow('quote', ['email' => 'rui@example.com']);

    $this->get(route('lynx.admin.forms.personal-data'))->assertOk()->assertSeeLivewire(PersonalDataSearch::class);
    $download = $this->get(route('lynx.admin.forms.personal-data.export', ['email' => 'ana@example.com']))->assertOk();
    expect(json_decode($download->streamedContent(), true)['submissions'])->toHaveCount(2);

    Livewire::test(PersonalDataSearch::class)->set('email', 'ana@')->assertDontSeeHtml('data-personal-result')
        ->set('email', ' ana@example.com ')->assertSeeHtml('data-personal-result="2"')
        ->call('confirmDelete')->assertSet('confirmingDelete', ['submissions' => 2, 'files' => 0])
        ->call('delete');

    expect(Submission::query()->pluck('data')->all())->toBe([['email' => 'rui@example.com']]);
});

it('keeps the Personal data tab from people who may not manage forms', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'production', 'grants' => [['permission' => 'admin.access'], ['permission' => 'forms.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['production']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    $this->get(route('lynx.admin.forms'))->assertOk()->assertDontSee(route('lynx.admin.forms.personal-data'));
    $this->get(route('lynx.admin.forms.personal-data'))->assertForbidden();
    $this->get(route('lynx.admin.forms.personal-data.export', ['email' => 'ana@example.com']))->assertForbidden();
});

it('deletes submissions older than their form\'s retention, in any status', function (): void {
    $page = lynx()->run(CreateForm::class, ['name' => 'contact', 'label' => 'Contact'])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $page->id, 'status' => [], 'values' => [
        FormSchema::FIELDS => [['type' => 'email', 'depth' => 0, 'values' => ['form_label' => ['default' => 'Email'], 'form_name' => 'email']]],
        FormSchema::RETENTION => 30,
    ]]);
    personalRow('contact', ['email' => 'old@example.com'], daysAgo: 40);
    personalRow('contact', ['email' => 'recent@example.com'], daysAgo: 10);
    personalRow('quote', ['email' => 'kept@example.com'], daysAgo: 400);

    expect(lynx()->run(PurgeSubmissions::class)->value)->toBe(1)
        ->and(Submission::query()->pluck('data')->pluck('email')->sort()->values()->all())->toBe(['kept@example.com', 'recent@example.com']);
});

it('applies a retention set in the config to forms without their own', function (): void {
    config(['lynx-forms.retention_days' => 90]);
    personalRow('quote', ['email' => 'old@example.com'], daysAgo: 100);
    personalRow('quote', ['email' => 'new@example.com'], daysAgo: 5);

    lynx()->run(PurgeSubmissions::class);

    expect(Submission::query()->count())->toBe(1);
});
