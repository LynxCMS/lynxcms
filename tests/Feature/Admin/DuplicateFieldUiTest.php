<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldList;

/*
 * Duplicate on the Fields screen: a dialog with a free name and the label filled in, then the new
 * field opens on its own screen.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('fills in a free name and the label', function (): void {
    lynx()->fields->add('summary_copy', 'text');

    Livewire::test(FieldList::class)->call('openDuplicate', 'summary')
        ->assertSet('duplicating', 'summary')
        ->assertSet('duplicateName', 'summary_copy_2')
        ->assertSet('duplicateLabel', lynx()->fields->find('summary')->label)
        ->assertDispatched('open-dialog-duplicate-field');
});

it('creates the field and opens it', function (): void {
    Livewire::test(FieldList::class)->call('openDuplicate', 'summary')->set('duplicateName', 'teaser')->call('duplicate')
        ->assertRedirect(route('lynx.admin.fields.edit', 'teaser'));

    expect(lynx()->fields->find('teaser')?->type)->toBe('textarea')
        ->and(lynx()->fields->find('teaser')?->labels()->toStorage())->toBe(lynx()->fields->find('summary')->labels()->toStorage());
});

it('shows why a name cannot be used', function (): void {
    Livewire::test(FieldList::class)->call('openDuplicate', 'summary')->set('duplicateName', 'body')->call('duplicate')
        ->assertHasErrors('name')->assertNoRedirect();
});
