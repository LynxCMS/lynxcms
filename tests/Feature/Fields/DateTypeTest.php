<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Query\SelectorException;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('starts_on', 'date');
    lynx()->fields->add('starts_at', 'date', settings: ['time' => true]);
    lynx()->templates->update('course', ['add' => ['starts_on', 'starts_at']]);
});

/** A course starting on $date. */
function courseStarting(string $name, ?string $date, ?string $dateTime = null): int
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = $name;
    $page->title = ucfirst($name);
    $page->starts_on = $date;
    $page->starts_at = $dateTime;
    $page->save();

    return (int) $page->id;
}

it('stores a date and wakes it as an immutable date', function (): void {
    $id = courseStarting('alpha', '2026-03-09');
    $value = lynx()->pages->get($id)?->starts_on;

    expect($value)->toBeInstanceOf(CarbonImmutable::class)
        ->and($value?->toDateString())->toBe('2026-03-09')
        ->and(lynx()->pages->get($id)?->getUnformatted('starts_on'))->toBeInstanceOf(CarbonImmutable::class);
});

it('keeps the time when the field has time, and drops it otherwise', function (): void {
    $id = courseStarting('alpha', '2026-03-09 14:30', '2026-03-09T14:30');
    $page = lynx()->pages->get($id);

    expect($page?->starts_on?->format('Y-m-d H:i'))->toBe('2026-03-09 00:00')
        ->and($page?->starts_at?->format('Y-m-d H:i'))->toBe('2026-03-09 14:30');
});

it('refuses text that is not a date', function (): void {
    courseStarting('alpha', 'next tuesday-ish');
})->throws(ValidationException::class);

it('compares dates in selectors, including relative ones', function (): void {
    courseStarting('past', now()->subDays(3)->toDateString());
    courseStarting('soon', now()->addDays(3)->toDateString());
    courseStarting('undated', null);

    expect(lynx()->pages->find('starts_on>=today')->map(fn ($p) => $p->name()))->toBe(['soon'])
        ->and(lynx()->pages->find('starts_on<'.now()->toDateString())->map(fn ($p) => $p->name()))->toBe(['past'])
        ->and(lynx()->pages->find('starts_on>=-7 days, sort=starts_on')->map(fn ($p) => $p->name()))->toBe(['past', 'soon']);
});

it('refuses a selector value that is not a date', function (): void {
    lynx()->pages->find('starts_on>soonish');
})->throws(SelectorException::class);

it('survives a content package round trip', function (): void {
    $id = courseStarting('alpha', '2026-03-09', '2026-03-09 14:30');
    $dir = sys_get_temp_dir().'/lynx-dates-'.uniqid();
    app(ContentExporter::class)->export($dir);
    $page = lynx()->pages->get($id);
    $page->starts_on = '2030-01-01';
    $page->save();

    $this->artisan('lynx:import', ['path' => $dir])->assertSuccessful();
    File::deleteDirectory($dir);

    expect(lynx()->pages->get($id)?->starts_on?->toDateString())->toBe('2026-03-09')
        ->and(lynx()->pages->get($id)?->starts_at?->format('H:i'))->toBe('14:30');
});
