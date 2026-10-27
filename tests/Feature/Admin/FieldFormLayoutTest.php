<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Editor\FieldKinds;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Core\Support\Value;

/*
 * The field form's layout: what a field holds as kinds in plain words, its sections in tabs (the
 * builder, Preview, Field, Limits and display), settings with their own controls, and formatters
 * as switches.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function creditsField(): void
{
    lynx()->fields->add('partner', 'text', true);
    lynx()->fields->add('credits', 'repeater', false, 'Credits', ['fields' => [['name' => 'partner']]]);
}

it('offers what a new field holds as kinds, in groups', function (): void {
    Livewire::test(FieldForm::class)
        ->assertSeeHtml('data-kind="textarea"')
        ->assertSeeHtml('data-kind="matrix"')
        ->assertSeeHtml('data-kind-group');
});

it('shows the kind of an existing field instead of the choice', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'summary'])
        ->assertDontSeeHtml('data-kind="matrix"')
        ->assertSeeHtml('data-field-kind="textarea"');
});

it('names unknown kinds from their type, and lets modules name theirs', function (): void {
    $kinds = app(FieldKinds::class);
    $kinds->register('rating', 'Star rating', 'Choices', 'star', 'One to five stars.');

    expect($kinds->for('rating'))->toMatchArray(['label' => 'Star rating', 'group' => 'Choices', 'icon' => 'star'])
        ->and($kinds->for('page_table')['label'])->not->toBe('page_table')
        ->and($kinds->for('mystery_type')['group'])->toBe(FieldKinds::OTHER);
});

it('puts a repeater\'s sections in tabs', function (): void {
    creditsField();

    Livewire::test(FieldForm::class, ['field' => 'credits'])
        ->assertSeeHtml('data-field-tab="builder"')
        ->assertSeeHtml('data-field-tab="preview"')
        ->assertSeeHtml('data-field-tab="field"')
        ->assertSeeHtml('data-field-tab="limits"')
        ->assertSeeHtml('data-item-limits');
});

it('gives other fields a Field and a Preview tab only', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'summary'])
        ->assertSeeHtml('data-field-tab="field"')
        ->assertSeeHtml('data-field-tab="preview"')
        ->assertDontSeeHtml('data-field-tab="builder"')
        ->assertDontSeeHtml('data-item-limits');
});

it('keeps the limits out of the generic settings', function (): void {
    creditsField();

    Livewire::test(FieldForm::class, ['field' => 'credits'])
        ->assertDontSeeHtml('id="setting-min"')
        ->assertDontSeeHtml('id="setting-add_label"');
});

it('saves the add button\'s text in every language', function (): void {
    creditsField();

    Livewire::test(FieldForm::class, ['field' => 'credits'])
        ->set('addLabel.default', 'Add a credit')
        ->set('addLabel.pt', 'Adicionar crédito')
        ->set('settings.min', '1')
        ->call('save')
        ->assertHasNoErrors();

    $config = lynx()->fields->find('credits')?->config() ?? [];

    expect($config['add_label'] ?? null)->toBe(['default' => 'Add a credit', 'pt' => 'Adicionar crédito'])
        ->and(Value::int($config['min'] ?? null))->toBe(1);
});

it('turns formatters on and off as switches', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'summary'])
        ->assertSeeHtml('data-formatter="smartypants"')
        ->call('toggleFormatter', 'smartypants')
        ->assertSet('formatters', ['smartypants'])
        ->call('toggleFormatter', 'nope')
        ->assertSet('formatters', ['smartypants'])
        ->call('toggleFormatter', 'smartypants')
        ->assertSet('formatters', []);
});

it('puts Save in the header', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'summary'])
        ->assertSeeHtml('form="field-form"');
});
