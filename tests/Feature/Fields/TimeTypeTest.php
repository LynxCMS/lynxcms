<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('opens', 'time');
    lynx()->fields->add('lap', 'time', settings: ['seconds' => true]);
    lynx()->templates->update('course', ['add' => ['opens', 'lap']]);
});

/** A course opening at a time of day. */
function timedCourse(string $name, mixed $opens, mixed $lap = null): int
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = $name;
    $page->title = ucfirst($name);
    $page->opens = $opens;
    $page->lap = $lap;
    $page->save();

    return (int) $page->id;
}

it('stores a time of day and reads it as HH:MM, or with seconds when the field keeps them', function (): void {
    $page = lynx()->pages->get(timedCourse('alpha', '9:05', '1:02:03'));

    expect($page?->opens)->toBe('09:05')
        ->and($page?->lap)->toBe('01:02:03')
        ->and(lynx()->pages->get(timedCourse('beta', ''))?->opens)->toBeNull();
});

it('accepts common ways of writing a time, and drops seconds the field does not keep', function (string $written, string $stored): void {
    expect(lynx()->pages->get(timedCourse('alpha', $written))?->opens)->toBe($stored);
})->with([
    ['14:30', '14:30'],
    ['14h30', '14:30'],
    ['7.45', '07:45'],
    ['23:59:59', '23:59'],
    ['0:00', '00:00'],
]);

it('refuses times that do not exist', function (mixed $written): void {
    expect(fn (): int => timedCourse('alpha', $written))->toThrow(ValidationException::class);
})->with(['24:00', '12:60', 'noon', '9']);

it('compares times in selectors', function (): void {
    timedCourse('early', '08:30');
    timedCourse('late', '18:00');

    $names = fn (string $selector): array => lynx()->pages->find('template=course, sort=name, '.$selector)->map(fn (Page $page): string => $page->name());

    expect($names('opens<12:00'))->toBe(['early'])
        ->and($names('opens>=12:00'))->toBe(['late'])
        ->and($names('opens=18:00'))->toBe(['late'])
        ->and($names('sort=opens, opens!='))->toBe(['early', 'late']);
});
