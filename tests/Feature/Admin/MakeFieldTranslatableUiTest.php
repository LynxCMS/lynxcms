<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;

/*
 * The field screen offers "Make translatable" for a shared field whose type can be translated, on
 * a site with one language too, since that is where shared text fields get made by mistake.
 */

beforeEach(function (): void {
    installLynx();
    lynx()->fields->add('venue', 'text');
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('offers to make a shared text field translatable, with one language too', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'venue'])->assertSee(__('lynx-admin::fields.languages.make_translatable'));
});

it('makes the field translatable', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'venue'])->call('makeTranslatable')->assertSet('translatable', true)
        ->assertDontSee(__('lynx-admin::fields.languages.make_translatable'));

    expect(lynx()->fields->find('venue')?->isTranslatable())->toBeTrue();
});

it('offers nothing for a field that is translatable or whose type can\'t be', function (string $field): void {
    lynx()->fields->add('code', 'text', translatable: true);
    lynx()->fields->add('seats', 'integer');

    Livewire::test(FieldForm::class, ['field' => $field])->assertDontSee(__('lynx-admin::fields.languages.make_translatable'));
})->with(['code', 'seats']);

it('refuses where schema editing is off', function (): void {
    config(['lynx.schema_editing' => false]);

    Livewire::test(FieldForm::class, ['field' => 'venue'])->call('makeTranslatable')->assertForbidden();
});
