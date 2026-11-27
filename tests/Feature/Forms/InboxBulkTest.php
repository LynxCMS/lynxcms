<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Forms\Actions\DeleteSubmissions;
use LynxCms\Forms\Actions\UpdateSubmissions;
use LynxCms\Forms\Admin\Livewire\Inbox;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\Models\SubmissionFile;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

/*
 * The inbox a page at a time, with numbered pages and a page size, a date filter, a selection of
 * a page or of every match, and bulk actions (forms.update_submissions, forms.delete_submissions)
 * that work through any number of submissions a chunk at a time.
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

/** Submissions straight into the table, oldest first, one a day before today; ids count up. */
function inboxRows(int $count, string $form = 'quote', string $status = 'new'): void
{
    foreach (range($count, 1) as $daysAgo) {
        Submission::query()->create([
            'uuid' => (string) Str::uuid(), 'form' => $form, 'data' => ['name' => "Person {$daysAgo}"], 'fields' => [],
            'status' => $status, 'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo),
        ]);
    }
}

it('shows a page at a time, with numbered pages and a page size', function (): void {
    inboxRows(60);

    Livewire::test(Inbox::class)->assertSeeHtml('data-page="3"')->assertSeeHtml('data-inbox-range')
        ->call('goToPage', 3)->assertSet('page', 3)
        ->assertSeeHtml('data-submission="'.Submission::query()->min('id').'"')->assertDontSeeHtml('data-submission="'.(Submission::query()->min('id') + 10).'"')
        ->set('perPage', 100)->assertSet('page', 1)->assertDontSeeHtml('data-page="2"');
});

it('filters by when submissions arrived', function (): void {
    inboxRows(40);

    // A row from $d days ago has the id first + 40 - $d (PostgreSQL's ids don't restart per test).
    $row = fn (int $daysAgo): string => 'data-submission="'.(Submission::query()->min('id') + 40 - $daysAgo).'"';

    Livewire::test(Inbox::class)->set('period', '7_days')->assertSeeHtml($row(6))->assertDontSeeHtml($row(8))
        ->set('period', 'custom')->set('from', now()->subDays(20)->toDateString())->set('to', now()->subDays(19)->toDateString())
        ->assertSeeHtml($row(20))->assertSeeHtml($row(19))->assertDontSeeHtml($row(18))->assertDontSeeHtml($row(21));
});

it('selects a page, then every submission that matches, and leaves later ones alone', function (): void {
    inboxRows(30);
    $component = Livewire::test(Inbox::class)->call('togglePage');

    expect($component->get('selected'))->toHaveCount(25);
    $component->assertSeeHtml('data-select-all-matching')->call('selectAllMatching')->assertSet('allMatching', true);
    inboxRows(1);
    $component->call('markSelected', 'archived');

    expect(Submission::query()->where('status', 'archived')->count())->toBe(30)
        ->and(Submission::query()->where('status', 'new')->count())->toBe(1);
});

it('unticks the page when it is all ticked', function (): void {
    inboxRows(3);

    Livewire::test(Inbox::class)->call('togglePage')->call('togglePage')->assertSet('selected', []);
});

it('deletes the selection with its files after saying how many go', function (): void {
    inboxRows(3);
    $first = Submission::query()->firstOrFail();
    SubmissionFile::query()->create(['submission_id' => $first->id, 'field' => 'brief', 'path' => 'x/brief.pdf', 'name' => 'brief.pdf', 'mime' => 'application/pdf', 'bytes' => 10]);

    Livewire::test(Inbox::class)->call('togglePage')->call('confirmDelete')
        ->assertSet('confirmingDelete', ['submissions' => 3, 'files' => 1])->assertSeeHtml('data-confirm-delete')
        ->call('deleteSelected');

    expect(Submission::query()->count())->toBe(0)->and(SubmissionFile::query()->count())->toBe(0);
});

it('offers delete only to people who may manage forms', function (): void {
    inboxRows(2);
    lynx()->run(SaveRole::class, ['name' => 'production', 'grants' => [['permission' => 'admin.access'], ['permission' => 'forms.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['production']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    Livewire::test(Inbox::class)->call('togglePage')->assertDontSeeHtml('data-inbox-delete')->call('confirmDelete')->assertForbidden();
});

it('marks every submission a filter matches, a chunk at a time, with one change', function (): void {
    inboxRows(3);
    inboxRows(2, status: 'spam');

    $result = lynx()->run(UpdateSubmissions::class, ['filter' => ['show' => 'new'], 'status' => 'read']);

    expect($result->value)->toBe(3)->and($result->changes->summary())->toHaveCount(1)
        ->and(Submission::query()->where('status', 'read')->whereNotNull('read_at')->count())->toBe(3);
});

it('says what a delete would remove on a dry run, and removes nothing', function (): void {
    inboxRows(4);

    expect(lynx()->run(DeleteSubmissions::class, ['filter' => ['show' => 'all']], dryRun: true)->value)->toBe(['submissions' => 4, 'files' => 0])
        ->and(Submission::query()->count())->toBe(4);
});

it('needs a list or a filter', function (): void {
    lynx()->run(DeleteSubmissions::class, []);
})->throws(ValidationException::class);

it('exports what the inbox shows', function (): void {
    inboxRows(5);
    inboxRows(2, status: 'spam');

    $csv = $this->get(route('lynx.admin.forms.export', ['form' => 'quote', 'show' => 'spam']))->streamedContent();

    expect(substr_count(trim($csv), "\n"))->toBe(2);
});

it('tells the sidebar the new count of unread submissions after a bulk action', function (): void {
    inboxRows(3);

    Livewire::test(Inbox::class)->call('togglePage')->call('markSelected', 'read')
        ->assertDispatched('lynx-nav-badge', route: 'lynx.admin.forms', value: 0);
});
