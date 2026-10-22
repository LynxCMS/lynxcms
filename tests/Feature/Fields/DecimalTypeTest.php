<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('rating', 'decimal', settings: ['places' => 1, 'min' => 0, 'max' => 5]);
    lynx()->fields->add('fee', 'decimal');
    lynx()->templates->update('course', ['add' => ['rating', 'fee']]);
});

/** A course with a rating and a fee. */
function ratedCourse(string $name, mixed $rating, mixed $fee = null): int
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = $name;
    $page->title = ucfirst($name);
    $page->rating = $rating;
    $page->fee = $fee;
    $page->save();

    return (int) $page->id;
}

it('stores numbers rounded to the field places and reads them as floats', function (): void {
    $id = ratedCourse('alpha', '4.36', '79.499');
    $page = lynx()->pages->get($id);

    expect($page?->rating)->toBe(4.4)
        ->and($page?->fee)->toBe(79.5)
        ->and(lynx()->pages->get(ratedCourse('beta', null))?->rating)->toBeNull();
});

it('accepts a decimal comma, and refuses text and numbers out of range', function (): void {
    expect(lynx()->pages->get(ratedCourse('alpha', '3,5'))?->rating)->toBe(3.5);

    expect(fn (): int => ratedCourse('beta', 'lots'))->toThrow(ValidationException::class)
        ->and(fn (): int => ratedCourse('gamma', 7))->toThrow(ValidationException::class);
});

it('compares numbers in selectors and sorts by them', function (): void {
    ratedCourse('low', 2.5);
    ratedCourse('high', 4.75);
    ratedCourse('mid', 3);

    expect(lynx()->pages->find('rating>=3, sort=-rating')->map(fn ($p) => $p->name()))->toBe(['high', 'mid'])
        ->and(lynx()->pages->find('rating=2.5')->map(fn ($p) => $p->name()))->toBe(['low']);
});
