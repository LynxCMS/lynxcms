<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Templates\Layout\TemplateLayout;

beforeEach(function (): void {
    installLynx();
    lynx()->fields->add('street', 'text', translatable: true, label: 'Street', settings: ['notes' => 'Number first']);
    lynx()->fields->add('city', 'text');
    lynx()->templates->add('contact', fields: ['title']);
    lynx()->templates->update('contact', [
        'tabs' => [['key' => 'details', 'label' => 'Details']],
        'fields' => [
            'title',
            ['section' => 'address', 'label' => 'Address', 'tab' => 'details', 'collapsed' => true, 'fields' => [
                ['name' => 'street', 'required' => true, 'label' => 'Street and number'],
                ['name' => 'city', 'show_if' => 'street!='],
            ]],
        ],
    ]);
    lynx()->templates->add('event', fields: ['title']);
    lynx()->templates->add('venue', fields: ['title', 'city']);
});

it('copies a section with its fields, their settings and its tab', function (): void {
    lynx()->templates->copySection('contact', 'address', to: ['event']);

    $fields = lynx()->templates->fields('event');
    $layout = TemplateLayout::for(lynx()->templates->get('event'));

    expect($fields->keys()->all())->toBe(['title', 'street', 'city'])
        ->and($fields['street']->settings)->toBe(['section' => 'address', 'required' => true, 'label' => 'Street and number'])
        ->and($fields['city']->settings['show_if'])->toBe('street!=')
        ->and($layout->section('address')?->collapsed)->toBeTrue()
        ->and($layout->tabOf('street'))->toBe('details')
        ->and(lynx()->templates->get('event')->config()['tabs'])->toBe([['key' => 'details', 'label' => 'Details']]);
});

it('copies to several templates in one run', function (): void {
    lynx()->fields->add('phone', 'text');
    lynx()->templates->add('office', fields: ['title', 'phone']);

    lynx()->templates->copySection('contact', 'address', to: ['event', 'office']);

    expect(lynx()->templates->fields('event')->has('street'))->toBeTrue()
        ->and(lynx()->templates->fields('office')->keys()->all())->toBe(['title', 'phone', 'street', 'city']);
});

it('refuses a template that already has one of the fields, changing nothing', function (): void {
    expect(fn () => lynx()->templates->copySection('contact', 'address', to: ['event', 'venue']))
        ->toThrow(ValidationException::class, 'Template [venue] already has [city]');

    expect(lynx()->templates->fields('event')->keys()->all())->toBe(['title']);
});

it('refuses a template whose section key is taken', function (): void {
    lynx()->fields->add('zip', 'text');
    lynx()->templates->update('event', ['add' => [['section' => 'address', 'fields' => ['zip']]]]);

    expect(fn () => lynx()->templates->copySection('contact', 'address', to: ['event']))
        ->toThrow(ValidationException::class, 'Template [event] already has a section [address]');
});

it('refuses an unknown section and copying a template onto itself', function (): void {
    expect(fn () => lynx()->templates->copySection('contact', 'nope', to: ['event']))
        ->toThrow(ValidationException::class, 'no section [nope]')
        ->and(fn () => lynx()->templates->copySection('contact', 'address', to: ['contact']))
        ->toThrow(ValidationException::class);
});

it('refuses a section whose condition reads a field the template lacks', function (): void {
    lynx()->fields->add('note', 'text');
    lynx()->templates->update('contact', ['add' => [['section' => 'extra', 'fields' => [['name' => 'note', 'show_if' => 'city=Lisbon']]]]]);

    expect(fn () => lynx()->templates->copySection('contact', 'extra', to: ['event']))
        ->toThrow(ValidationException::class, 'reads [city]');
});

it('renames the fields that clash, cloning them, when asked', function (): void {
    lynx()->templates->copySection('contact', 'address', to: ['venue'], renameConflicts: true);

    $fields = lynx()->templates->fields('venue');
    $clone = lynx()->fields->get('address_city');

    expect($fields->keys()->all())->toBe(['title', 'city', 'street', 'address_city'])
        ->and($fields['address_city']->settings)->toBe(['section' => 'address', 'show_if' => 'street!='])
        ->and($clone->type)->toBe('text')
        ->and($clone->isTranslatable())->toBeFalse();
});

it('uses the prefix it is given and rewrites conditions that read a renamed field', function (): void {
    lynx()->templates->update('venue', ['add' => ['street']]);

    lynx()->templates->copySection('contact', 'address', to: ['venue'], renameConflicts: true, prefix: 'billing_');

    $street = lynx()->fields->get('billing_street');

    expect(lynx()->templates->fields('venue')['billing_city']->settings['show_if'])->toBe('billing_street!=')
        ->and($street->isTranslatable())->toBeTrue()
        ->and($street->labels()->in())->toBe('Street')
        ->and($street->notes())->toBe('Number first');
});

it('creates a renamed field once for every template that needs it', function (): void {
    lynx()->templates->add('office', fields: ['title', 'city']);

    lynx()->templates->copySection('contact', 'address', to: ['venue', 'office'], renameConflicts: true);

    expect(lynx()->templates->fields('venue')->has('address_city'))->toBeTrue()
        ->and(lynx()->templates->fields('office')->has('address_city'))->toBeTrue();
});

it('refuses a renamed field whose name is taken, changing nothing', function (): void {
    lynx()->fields->add('address_city', 'text');

    expect(fn () => lynx()->templates->copySection('contact', 'address', to: ['event', 'venue'], renameConflicts: true))
        ->toThrow(ValidationException::class, 'Field [address_city] already exists');

    expect(lynx()->templates->fields('event')->keys()->all())->toBe(['title']);
});

it('refuses a prefix that cannot start a field name', function (): void {
    expect(fn () => lynx()->templates->copySection('contact', 'address', to: ['venue'], renameConflicts: true, prefix: '9-'))
        ->toThrow(ValidationException::class);
});

it('writes nothing on a dry run', function (): void {
    $result = lynx()->templates->copySection('contact', 'address', to: ['venue'], renameConflicts: true, dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and(lynx()->templates->fields('venue')->keys()->all())->toBe(['title', 'city'])
        ->and(lynx()->fields->find('address_city'))->toBeNull();
});
