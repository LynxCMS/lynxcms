<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/*
 * lynx:templates and lynx:fields list the schema like route:list does routes: templates with
 * their pages, fields and views; a template's fields in order; every field with where it is used.
 * --json gives the same for scripts and agents.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    lynx()->fields->add('caption', 'text');
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'caption']]]);
    lynx()->fields->add('subtitle', 'text');
    lynx()->templates->update('course', ['add' => ['credits']]);
});

/** @return array<array-key, mixed> */
function listed(string $command, array $arguments = []): array
{
    expect(Artisan::call($command, [...$arguments, '--json' => true]))->toBe(0);

    return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
}

it('lists the templates with their pages and fields', function (): void {
    $course = collect(listed('lynx:templates'))->keyBy('name')['course'];

    expect($course)->toMatchArray(['name' => 'course', 'has_url' => true, 'pages' => 2, 'fields' => 6])
        ->toHaveKey('view');
});

it('lists one template\'s fields in order', function (): void {
    $fields = listed('lynx:templates', ['template' => 'course']);

    expect(array_column($fields, 'name'))->toBe(['title', 'summary', 'body', 'price', 'related', 'credits'])
        ->and($fields[0])->toMatchArray(['type' => 'text', 'required' => true])
        ->and($fields[1])->toMatchArray(['type' => 'textarea', 'translatable' => true]);
});

it('lists every field with where it is used', function (): void {
    $fields = collect(listed('lynx:fields'))->keyBy('name');

    expect($fields['price']['templates'])->toBe(['course'])
        ->and($fields['caption']['items'])->toBe(['credits'])
        ->and($fields['subtitle']['templates'])->toBe([]);
});

it('lists only the unused fields when asked', function (): void {
    expect(array_column(listed('lynx:fields', ['--unused' => true]), 'name'))->toBe(['subtitle']);
});

it('refuses an unknown template', function (): void {
    $this->artisan('lynx:templates', ['template' => 'nope'])->assertFailed();
});

it('prints tables', function (): void {
    $this->artisan('lynx:templates')->assertSuccessful();
    $this->artisan('lynx:templates', ['template' => 'course'])->assertSuccessful();
    $this->artisan('lynx:fields')->assertSuccessful();
});
