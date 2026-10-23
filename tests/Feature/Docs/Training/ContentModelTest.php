<?php

declare(strict_types=1);

use Database\Seeders\AtlasContentSeeder;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;

/*
 * Chapter 2 of the training site guide: the content model in docs/snippets/training.
 */

require_once __DIR__.'/training.php';

beforeEach(function (): void {
    trainingSite(withContent: false);
});

it('makes English and Portuguese, with Portuguese falling back to English', function (): void {
    expect(lynx()->languages->default()->code)->toBe('en')
        ->and(lynx()->languages->find('pt')?->fallback?->code)->toBe('en');
});

it('keeps sessions under their course, and courses under the catalogue', function (): void {
    $catalogue = lynx()->pages->new('courses', '/');
    $catalogue->name = 'courses';
    $catalogue->title = 'Courses';
    $catalogue->save();

    $course = lynx()->pages->new('course', '/courses/');
    $course->name = 'yoga-teacher-training';
    $course->title = 'Yoga teacher training';
    $course->summary = 'Two hundred hours.';
    $course->level = 'beginner';
    $course->save();

    expect(fn () => lynx()->pages->new('session', '/')->fill('en', ['title' => 'Nowhere'])->save())->toThrow(ValidationException::class);

    expect(lynx()->pages->find('template=course, level=beginner')->count())->toBe(1)
        ->and(lynx()->pages->get('/courses/yoga-teacher-training/'))->toBeInstanceOf(Page::class);
});

it('seeds the demo school in both languages', function (): void {
    require_once __DIR__.'/../../../../docs/snippets/training/database/seeders/AtlasContentSeeder.php';
    new AtlasContentSeeder()->run();

    expect(lynx()->pages->count('template=course'))->toBe(6)
        ->and(lynx()->pages->count('template=session, starts>=today'))->toBe(12)
        ->and(lynx()->pages->count('template=instructor'))->toBe(3)
        ->and((string) lynx()->pages->get('/courses/sports-nutrition/')?->getLanguageValue('pt', 'title'))->toBe('Nutrição desportiva')
        ->and(lynx()->pages->get('/courses/sports-nutrition/')?->localUrl('pt'))->toEndWith('/pt/cursos/nutricao-desportiva')
        ->and(lynx()->settings()?->email)->not->toBeNull()
        ->and(lynx()->pages->get('/')?->featured->count())->toBe(3);
});
