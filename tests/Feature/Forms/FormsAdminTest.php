<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Admin\Livewire\Inbox;
use LynxCms\Forms\Admin\Livewire\SubmissionView;
use LynxCms\Forms\Models\Submission;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    foreach (['Ana Lima' => 'ana@example.com', 'Rui Costa' => 'rui@example.com'] as $name => $email) {
        lynx()->run(SubmitForm::class, ['form' => 'quote', 'language' => 'pt', 'data' => [
            'type' => 'film', 'name' => $name, 'email' => $email, 'consent' => true,
            'brief' => [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')],
        ]]);
    }
    $this->ana = Submission::query()->where('data->name', 'Ana Lima')->sole();
    $this->actingAs(new GenericUser(['id' => 1]));
});

/** Roles: user 1 a superuser, user 2 may see submissions, user 3 may only view pages. */
function formRoles(): void
{
    lynx()->run(SaveRole::class, ['name' => 'production', 'grants' => [['permission' => 'admin.access'], ['permission' => 'forms.view']]]);
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['production']]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['editor']]);
}

it('adds Forms to the admin with the number of new submissions', function (): void {
    $this->get('/lynx/pages')->assertOk()->assertSee('Forms')->assertSeeInOrder(['Forms', '2']);
});

it('keeps the inbox from users who may not see submissions', function (): void {
    formRoles();
    $this->actingAs(new GenericUser(['id' => 3]));

    expect(array_column(app(Navigation::class)->items(), 'route'))->not->toContain('lynx.admin.forms');
    $this->get('/lynx/forms')->assertForbidden();
    $this->get(route('lynx.admin.forms.show', $this->ana->uuid))->assertForbidden();
});

it('lists submissions by form and status, and finds them by text', function (): void {
    Livewire::test(Inbox::class)->assertSee('Ana Lima')->assertSee('Rui Costa')
        ->set('search', 'rui')->assertSee('Rui Costa')->assertDontSee('Ana Lima')
        ->set('search', '')->set('show', 'archived')->assertDontSee('Ana Lima');
});

it('marks the selected submissions in one go', function (): void {
    Livewire::test(Inbox::class)->set('selected', [$this->ana->uuid])->call('markSelected', 'archived')
        ->assertDispatched('lynx-notify');

    expect($this->ana->fresh()?->status)->toBe('archived');
});

it('shows a submission\'s answers and marks it read', function (): void {
    Livewire::test(SubmissionView::class, ['uuid' => $this->ana->uuid])
        ->assertSee('Project type')->assertSee('Film')->assertSee('brief.pdf');

    expect($this->ana->fresh()?->status)->toBe('read');
});

it('keeps notes and changes the status', function (): void {
    Livewire::test(SubmissionView::class, ['uuid' => $this->ana->uuid])
        ->set('notes', 'Call on Monday')->call('saveNotes')->call('setStatus', 'archived');

    expect($this->ana->fresh()?->notes)->toBe('Call on Monday')->and($this->ana->fresh()?->status)->toBe('archived');
});

it('deletes a submission only for users who may manage them', function (): void {
    formRoles();
    $this->actingAs(new GenericUser(['id' => 2]));
    Livewire::test(SubmissionView::class, ['uuid' => $this->ana->uuid])->assertDontSee('Yes, delete');
    Livewire::test(SubmissionView::class, ['uuid' => $this->ana->uuid])->call('delete')->assertForbidden();

    $this->actingAs(new GenericUser(['id' => 1]));
    Livewire::test(SubmissionView::class, ['uuid' => $this->ana->uuid])->call('delete')->assertRedirect(route('lynx.admin.forms'));
    expect(Submission::query()->count())->toBe(1);
});

it('serves files only to users who may see submissions', function (): void {
    $file = $this->ana->files->first();
    $url = route('lynx.admin.forms.file', [$this->ana->uuid, $file->id]);

    $this->get($url)->assertOk()->assertDownload('brief.pdf');

    formRoles();
    $this->actingAs(new GenericUser(['id' => 3]));
    $this->get($url)->assertForbidden();
});

it('exports a form\'s submissions as CSV', function (): void {
    $csv = $this->get(route('lynx.admin.forms.export', 'quote'))->assertOk()->streamedContent();

    expect($csv)->toContain('Project type')->toContain('Ana Lima')->toContain('Rui Costa');
});
