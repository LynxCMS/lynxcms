<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Editor\SettingLabels;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionResult;

/*
 * A page field can hold a number of pages between `min` and `max`, as repeaters and matrices hold
 * items: "at most 8 cards", "at least 2 related courses". Zero pages is left to `required`.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta', 'gamma', 'delta');
});

/** @param  list<string>  $names */
function relatedIds(array $names): array
{
    return array_map(fn (string $name): int => (int) lynx()->pages->get("/courses/{$name}/")?->id, $names);
}

function saveRelated(array $names): void
{
    $page = lynx()->pages->get('/courses/alpha/');
    $page->related = relatedIds($names);
    $page->save();
}

it('refuses more pages than the field allows', function (): void {
    lynx()->fields->update('related', ['settings' => ['max' => 2]]);

    expect(fn () => saveRelated(['beta', 'gamma', 'delta']))->toThrow(ValidationException::class, 'at most 2');
});

it('takes up to the most pages allowed', function (): void {
    lynx()->fields->update('related', ['settings' => ['max' => 2]]);
    saveRelated(['beta', 'gamma']);

    expect(lynx()->pages->get('/courses/alpha/')?->related->count())->toBe(2);
});

it('refuses fewer pages than the field needs, but leaves none to required', function (): void {
    lynx()->fields->update('related', ['settings' => ['min' => 2]]);

    expect(fn () => saveRelated(['beta']))->toThrow(ValidationException::class, 'at least 2');

    saveRelated([]);
    expect(lynx()->pages->get('/courses/alpha/')?->related->count())->toBe(0);
});

it('counts the pages a dry run would save', function (): void {
    lynx()->fields->update('related', ['settings' => ['max' => 1]]);

    expect(fn (): ActionResult => lynx()->run(SavePage::class, ['id' => (int) lynx()->pages->get('/courses/alpha/')?->id, 'values' => ['related' => relatedIds(['beta', 'gamma'])]], dryRun: true))
        ->toThrow(ValidationException::class, 'at most 1');
});

it('refuses limits below zero', function (string $setting): void {
    lynx()->fields->update('related', ['settings' => [$setting => -1]]);
})->with(['min', 'max'])->throws(ValidationException::class);

it('limits a page field inside repeater items too', function (): void {
    lynx()->fields->update('related', ['settings' => ['max' => 1]]);
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'related']]]);
    lynx()->templates->update('course', ['add' => ['credits']]);
    $page = lynx()->pages->get('/courses/alpha/');
    $page->credits = [['values' => ['related' => relatedIds(['beta', 'gamma'])]]];

    expect(fn () => $page->save())->toThrow(ValidationException::class, 'at most 1');
});

it('words the limits as pages on the field form, not as the number field\'s values', function (): void {
    $definition = ['type' => 'integer', 'description' => '', 'enum' => []];
    $labels = app(SettingLabels::class);

    expect($labels->for('max', $definition, 'page')['label'])->not->toBe($labels->for('max', $definition, 'integer')['label'])
        ->and($labels->for('min', $definition, 'page')['label'])->not->toBe($labels->for('min', $definition, 'integer')['label']);
});
