<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Admin\Livewire\FieldList;
use LynxCms\Admin\Livewire\TemplateForm;

beforeEach(function (): void {
    courseSite();
    lynx()->menus()->create('top', 'Top');
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('shows schema owned by a module as read only, naming the module', function (): void {
    expect(app(SchemaOwners::class)->ownerOfField('menu_page'))->toBe('Menus')
        ->and(app(SchemaOwners::class)->ownerOfTemplate('menu-item'))->toBe('Menus');

    Livewire::test(FieldForm::class, ['field' => 'menu_page'])
        ->assertSee('Managed by the Menus module')
        ->assertDontSeeHtml('wire:click="confirmDelete"')
        ->call('save')->assertForbidden();

    Livewire::test(TemplateForm::class, ['template' => 'menu-item'])
        ->assertSee('Managed by the Menus module')
        ->call('saveFields')->assertForbidden();
});

it('labels module fields in the list', function (): void {
    Livewire::test(FieldList::class)->assertDontSee('system · Menus')->set('showSystem', true)->assertSee('system · Menus');
});

it('makes every schema screen read only when schema editing is off', function (): void {
    config(['lynx-admin.schema_editing' => false]);

    $this->get('/lynx/fields')->assertOk()->assertDontSee('New field')->assertSee('lynx:schema:apply');
    $this->get('/lynx/templates')->assertOk()->assertDontSee('New template');
    $this->get('/lynx/fields/create')->assertForbidden();

    Livewire::test(FieldForm::class, ['field' => 'summary'])->assertSee('lynx:schema:apply')->call('save')->assertForbidden();
    Livewire::test(TemplateForm::class, ['template' => 'course'])->call('saveFamily')->assertForbidden();
    Livewire::test(TemplateForm::class, ['template' => 'course'])->call('confirmDelete')->assertForbidden();
});

it('allows schema editing by default', function (): void {
    expect(config('lynx-admin.schema_editing'))->toBeTrue();
});
