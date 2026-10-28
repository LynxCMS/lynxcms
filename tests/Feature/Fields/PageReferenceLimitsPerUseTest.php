<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

/*
 * A template, or a block type of a matrix, may set a page field's min, max and templates for
 * itself (ADR-0094): one "cards" field holds 4 pages in one section and 8 in another.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta', 'gamma');
    lynx()->templates->add('landing', fields: [['name' => 'title', 'required' => true], 'related']);
});

/** @param  list<string>  $names */
function courseIds(array $names): array
{
    return array_map(fn (string $name): int => (int) lynx()->pages->get("/courses/{$name}/")?->id, $names);
}

function landingWith(array $related): void
{
    $page = lynx()->pages->new('landing', '/');
    $page->name = 'landing-'.count($related);
    $page->title = 'Landing';
    $page->related = $related;
    $page->save();
}

it('takes a template\'s own most pages over the field\'s', function (): void {
    lynx()->fields->update('related', ['settings' => ['max' => 3]]);
    lynx()->templates->update('landing', ['fields' => [['name' => 'title', 'required' => true], ['name' => 'related', 'max' => 1]]]);

    expect(fn () => landingWith(courseIds(['alpha', 'beta'])))->toThrow(ValidationException::class, 'at most 1');

    $course = lynx()->pages->get('/courses/alpha/');
    $course->related = courseIds(['beta', 'gamma']);
    $course->save();

    expect(lynx()->pages->get('/courses/alpha/')?->related->count())->toBe(2);
});

it('takes a template\'s own fewest pages', function (): void {
    lynx()->templates->update('landing', ['fields' => [['name' => 'title', 'required' => true], ['name' => 'related', 'min' => 2]]]);

    expect(fn () => landingWith(courseIds(['alpha'])))->toThrow(ValidationException::class, 'at least 2');
});

it('takes a block type\'s own limits for a page field in its blocks', function (): void {
    lynx()->fields->add('sections', 'matrix', settings: ['types' => [
        ['slug' => 'four', 'label' => 'Four cards', 'fields' => [['name' => 'related', 'max' => 1]]],
        ['slug' => 'many', 'label' => 'Many cards', 'fields' => [['name' => 'related']]],
    ]]);
    lynx()->templates->update('landing', ['add' => ['sections']]);
    $page = lynx()->pages->new('landing', '/');
    $page->name = 'sections';
    $page->title = 'Sections';
    $page->sections = [['type' => 'many', 'values' => ['related' => courseIds(['alpha', 'beta'])]]];
    $page->save();

    $page->sections = [['type' => 'four', 'values' => ['related' => courseIds(['alpha', 'beta'])]]];

    expect(fn () => $page->save())->toThrow(ValidationException::class, 'at most 1');
});

it('refuses limits a field type does not take per use, and limits below zero', function (array $entry): void {
    lynx()->templates->update('landing', ['fields' => [['name' => 'title', 'required' => true], ['name' => 'related'], $entry]]);
})->with([
    'max on a text field' => [['name' => 'summary', 'max' => 2]],
    'templates on a number field' => [['name' => 'price', 'templates' => ['course']]],
    'negative max' => [['name' => 'related', 'max' => -1]],
])->throws(ValidationException::class);

it('refuses a block type\'s limits for a field that takes none', function (): void {
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [['slug' => 'text', 'label' => 'Text', 'fields' => [['name' => 'summary', 'max' => 2]]]]]);
})->throws(ValidationException::class);

it('lists and limits pages in the editor by the template\'s own settings', function (): void {
    test()->actingAs(new GenericUser(['id' => 1]));
    lynx()->templates->update('landing', ['fields' => [['name' => 'title', 'required' => true], ['name' => 'related', 'max' => 1, 'templates' => ['course']]]]);
    landingWith(courseIds(['alpha']));

    Livewire::test(PageEditor::class, ['pageId' => lynx()->pages->get('/landing-1/')?->id])->assertSeeHtml('data-picker-full');
});

it('takes a template\'s own most items for a repeater', function (): void {
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'related']]]);
    lynx()->templates->update('landing', ['fields' => [['name' => 'title', 'required' => true], ['name' => 'credits', 'max' => 1]]]);
    $page = lynx()->pages->new('landing', '/');
    $page->name = 'credits';
    $page->title = 'Credits';
    $page->credits = [['values' => ['related' => courseIds(['alpha'])]], ['values' => ['related' => courseIds(['beta'])]]];

    expect(fn () => $page->save())->toThrow(ValidationException::class, 'at most 1');
});
