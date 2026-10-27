<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('level', 'choice', settings: ['default' => 'beginner', 'options' => [
        ['value' => 'beginner', 'label' => 'Beginner'],
        ['value' => 'advanced', 'label' => 'Advanced'],
    ]]);
    lynx()->fields->add('tags', 'choice', settings: ['multiple' => true, 'default' => ['online', 'free'], 'options' => [
        ['value' => 'online', 'label' => 'Online'],
        ['value' => 'free', 'label' => 'Free'],
        ['value' => 'certified', 'label' => 'Certified'],
    ]]);
    lynx()->templates->update('course', ['add' => ['level', 'tags']]);
});

function newCourseWithDefaults(array $values = []): int
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'alpha';
    $page->title = 'Alpha';
    $page->fill('default', $values);
    $page->save();

    return (int) $page->id;
}

it('stores the default on a new page that leaves the field out', function (): void {
    $page = lynx()->pages->get(newCourseWithDefaults());

    expect($page->level?->value)->toBe('beginner')
        ->and($page->tags->values())->toBe(['online', 'free'])
        ->and(lynx()->pages->count('template=course, level=beginner'))->toBe(1);
});

it('keeps a value given on create, and an explicit empty one', function (): void {
    $page = lynx()->pages->get(newCourseWithDefaults(['level' => 'advanced', 'tags' => null]));

    expect($page->level?->value)->toBe('advanced')
        ->and($page->tags->values())->toBe([]);
});

it('leaves existing pages alone', function (): void {
    $id = newCourseWithDefaults(['level' => null]);
    lynx()->run(SavePage::class, ['id' => $id, 'values' => ['title' => 'Alpha 2']]);

    expect(lynx()->pages->get($id)->level)->toBeNull();
});

it('stores the default in new repeater items, not in existing ones', function (): void {
    lynx()->fields->add('sessions', 'repeater', settings: ['fields' => [['name' => 'summary'], ['name' => 'level']]]);
    lynx()->templates->update('course', ['add' => ['sessions']]);
    $id = newCourseWithDefaults();

    lynx()->run(SavePage::class, ['id' => $id, 'values' => ['sessions' => [['values' => ['summary' => 'One']], ['values' => ['summary' => 'Two', 'level' => 'advanced']]]]]);
    $items = lynx()->pages->get($id)->sessions->all();
    expect([$items[0]->level?->value, $items[1]->level?->value])->toBe(['beginner', 'advanced']);

    $kept = array_map(fn ($item): array => ['key' => $item->key(), 'values' => ['summary' => 'Kept', 'level' => null]], $items);
    lynx()->run(SavePage::class, ['id' => $id, 'values' => ['sessions' => $kept]]);
    expect(array_map(fn ($item) => $item->level, lynx()->pages->get($id)->sessions->all()))->toBe([null, null]);
});

it('refuses a default that is not one of the options', function (): void {
    expect(fn () => lynx()->fields->add('size', 'choice', settings: ['default' => 'huge', 'options' => [['value' => 'small', 'label' => 'Small']]]))
        ->toThrow(ValidationException::class, 'The default [huge] is not one of the options.');
});

it('refuses options that leave the default out', function (): void {
    expect(fn () => lynx()->fields->update('level', ['settings' => ['options' => [['value' => 'advanced', 'label' => 'Advanced']]]]))
        ->toThrow(ValidationException::class, 'The default [beginner] is not one of the options.');
});

it('takes one default for a single choice and a list for several', function (): void {
    expect(fn () => lynx()->fields->update('level', ['settings' => ['default' => ['beginner', 'advanced']]]))
        ->toThrow(ValidationException::class, 'A single choice takes one default.');
});
