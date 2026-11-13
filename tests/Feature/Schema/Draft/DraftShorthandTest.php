<?php

declare(strict_types=1);

use LynxCms\Core\Schema\Draft\DraftError;
use LynxCms\Core\Schema\Draft\DraftShorthand;

/*
 * The short field syntax of a draft (ADR-0116): "type[:argument] token token", where the argument
 * fills the type's main setting and tokens are flags or key:value settings. The long form is the
 * field as an array.
 */

function draftField(string $name, mixed $definition): array
{
    return app(DraftShorthand::class)->field($name, $definition);
}

it('maps a type\'s argument to its main setting', function (): void {
    expect(draftField('price', 'decimal:2'))->toBe([
        'name' => 'price',
        'type' => 'decimal',
        'label' => 'Price',
        'translatable' => false,
        'settings' => ['places' => 2],
    ]);
});

it('labels a field after its name', function (): void {
    expect(draftField('max_seats', 'integer')['label'])->toBe('Max seats');
});

it('reads choice options, quoted ones with spaces too', function (): void {
    expect(draftField('level', 'choice:Beginner,"Very advanced"')['settings']['options'])->toBe([
        ['value' => 'beginner', 'label' => 'Beginner'],
        ['value' => 'very-advanced', 'label' => 'Very advanced'],
    ]);
});

it('marks a field translatable', function (): void {
    expect(draftField('summary', 'textarea translatable')['translatable'])->toBeTrue();
});

it('turns a flag into a true setting', function (): void {
    expect(draftField('related', 'page multiple')['settings'])->toBe(['multiple' => true]);
});

it('splits a list setting on commas', function (): void {
    expect(draftField('related', 'page templates:course,courses')['settings'])->toBe(['templates' => ['course', 'courses']]);
});

it('casts numbers in key:value settings', function (): void {
    expect(draftField('seats', 'integer min:0 max:40')['settings'])->toBe(['min' => 0, 'max' => 40]);
});

it('accepts the settings every field has', function (): void {
    expect(draftField('notes', 'textarea fallback:none autoload')['settings'])->toBe(['fallback' => 'none', 'autoload' => true]);
});

it('takes a label as a token, quoted when it has spaces', function (): void {
    expect(draftField('cta_label', 'text translatable label:"Button text"'))
        ->toMatchArray(['label' => 'Button text', 'translatable' => true, 'settings' => []]);
});

it('reads the long form, with a label of its own', function (): void {
    expect(draftField('price', ['type' => 'decimal', 'label' => 'Price (EUR)', 'places' => 2]))
        ->toMatchArray(['label' => 'Price (EUR)', 'settings' => ['places' => 2]]);
});

it('reads a repeater\'s fields, a star marking a required one', function (): void {
    expect(draftField('speakers', ['type' => 'repeater', 'fields' => ['speaker_name*', 'speaker_photo']])['settings'])
        ->toBe(['fields' => [['name' => 'speaker_name', 'required' => true], ['name' => 'speaker_photo']]]);
});

it('suggests the type meant by an unknown one', function (): void {
    draftField('price', 'decmal:2');
})->throws(DraftError::class, 'Did you mean [decimal]?');

it('suggests the setting meant by an unknown one, and lists the known ones', function (): void {
    expect(fn (): array => draftField('price', 'decimal plces:2'))->toThrow(function (DraftError $error): void {
        expect($error->getMessage())->toContain('Field [price]')
            ->toContain('Did you mean [places]?')
            ->toContain('places, min, max');
    });
});

it('refuses an argument on a type that takes none', function (): void {
    draftField('open', 'toggle:yes');
})->throws(DraftError::class, 'takes no argument');
