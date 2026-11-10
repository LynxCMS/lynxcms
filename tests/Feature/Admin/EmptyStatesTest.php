<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Livewire\FieldList;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Admin\Livewire\RoleList;
use LynxCms\Core\Permissions\Role;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Admin\Livewire\Inbox;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

/*
 * What lists show when they have nothing to show: an empty state saying what would be there and
 * how it gets there, and when a filter found nothing, a way to clear it.
 */

beforeEach(function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('offers to create the first field on a site with none of its own', function (): void {
    installLynx();
    // Fields a module owns are listed only on request, so this site has none of its own.
    app(SchemaOwners::class)->register('Test', [], array_map(strval(...), lynx()->fields->all()->pluck('name')->all()));

    Livewire::test(FieldList::class)
        ->assertSeeHtml('data-empty-state')
        ->assertSeeHtml('href="'.route('lynx.admin.fields.create').'"')
        ->assertDontSeeHtml('wire:click="clearFilters"');
});

it('offers to clear the filters when they find no field', function (): void {
    courseSite();

    Livewire::test(FieldList::class)
        ->set('search', 'no-such-field')
        ->assertSeeHtml('data-empty-state')
        ->assertSeeHtml('wire:click="clearFilters"')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertDontSeeHtml('data-empty-state');
});

it('offers to create the first role', function (): void {
    courseSite();
    Role::query()->delete();

    Livewire::test(RoleList::class)
        ->assertSeeHtml('data-empty-state')
        ->assertSeeHtml('href="'.route('lynx.admin.roles.create').'"');
});

it('shows an empty state for a page tree filter that finds nothing', function (): void {
    courseSite();

    Livewire::test(PageTree::class)->call('showDetails', true)->call('filterBy', 'drafts')
        ->assertSeeHtml('data-tree-filtered')
        ->assertSeeHtml('data-empty-state');
});

it('tells an empty inbox from a filter that finds nothing', function (): void {
    courseSite();
    lynx()->forms()->register(QuoteForm::class);

    Livewire::test(Inbox::class)->assertSeeHtml('data-empty-state')->assertDontSeeHtml('wire:click="clearFilters"');

    lynx()->run(SubmitForm::class, ['form' => 'quote', 'language' => 'pt', 'data' => ['type' => 'film', 'name' => 'Ana Lima', 'email' => 'ana@example.com', 'consent' => true]]);

    Livewire::test(Inbox::class)
        ->set('search', 'nobody')
        ->assertSeeHtml('wire:click="clearFilters"')
        ->call('clearFilters')
        ->assertSet('show', 'all')
        ->assertSet('search', '')
        ->assertDontSeeHtml('data-empty-state');
});
