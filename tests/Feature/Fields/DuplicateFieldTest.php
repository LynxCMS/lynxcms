<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Models\Field;

/*
 * fields.duplicate makes a new field like another: same type, labels, translation and settings,
 * under a new name. It is a new field: no template uses it and it holds no values yet.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('tagline', 'text', translatable: true, label: ['default' => 'Tagline', 'pt' => 'Lema'], settings: ['max_length' => 80, 'notes' => 'Short']);
});

it('copies the type, labels, translation and settings under a new name', function (): void {
    $copy = lynx()->fields->duplicate('tagline', 'subtitle');

    expect($copy)->toBeInstanceOf(Field::class)
        ->and($copy->type)->toBe('text')
        ->and($copy->labels()->toStorage())->toBe(['default' => 'Tagline', 'pt' => 'Lema'])
        ->and($copy->isTranslatable())->toBeTrue()
        ->and($copy->config())->toMatchArray(['max_length' => 80, 'notes' => 'Short'])
        ->and(lynx()->templates->fields('course')->has('subtitle'))->toBeFalse();
});

it('takes a new label when given one', function (): void {
    expect(lynx()->fields->duplicate('tagline', 'subtitle', label: 'Subtitle')->label)->toBe('Subtitle');
});

it('refuses a name that is taken or a field that does not exist', function (): void {
    expect(fn () => lynx()->fields->duplicate('tagline', 'summary'))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->fields->duplicate('nope', 'other'))->toThrow(ValidationException::class);
});

it('writes nothing on a dry run', function (): void {
    expect(lynx()->fields->duplicate('tagline', 'subtitle', dryRun: true))->toBeInstanceOf(ActionResult::class)
        ->and(lynx()->fields->find('subtitle'))->toBeNull();
});
