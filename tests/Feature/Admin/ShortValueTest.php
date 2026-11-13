<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use LynxCms\Admin\Fields\ShortValue;

/*
 * A field value as short text for table cells (ShortValue), as the page table input and the
 * finder's columns show it.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('featured', 'toggle');
    lynx()->fields->add('starts_on', 'date');
    lynx()->fields->add('starts_at', 'date', settings: ['time' => true]);
    lynx()->fields->add('gallery', 'images');
    lynx()->templates->update('course', ['add' => [['name' => 'featured'], ['name' => 'starts_on'], ['name' => 'starts_at'], ['name' => 'gallery']]]);
    $this->course = createCourses('alpha')['alpha'];
});

it('shows a day in the locale format, and the time only when it has one', function (): void {
    $this->course->starts_on = '2026-07-29';
    $this->course->starts_at = '2026-07-29 14:30:00';
    $this->course->save();

    expect(ShortValue::of($this->course, 'starts_on'))->toBe(CarbonImmutable::parse('2026-07-29')->isoFormat('ll'))
        ->and(ShortValue::of($this->course, 'starts_at'))->toBe(CarbonImmutable::parse('2026-07-29 14:30')->isoFormat('lll'));
});

it('shows yes or no for a switch', function (): void {
    $this->course->featured = true;
    $this->course->save();

    expect(ShortValue::of($this->course, 'featured'))->toBe(__('lynx-admin::editor.value.yes'));
});

it('cuts long text and drops its tags', function (): void {
    $this->course->summary = '<p>'.str_repeat('a', ShortValue::MAX_LENGTH + 20).'</p>';
    $this->course->save();

    expect(mb_strlen(ShortValue::of($this->course, 'summary')))->toBe(ShortValue::MAX_LENGTH)
        ->and(ShortValue::of($this->course, 'summary'))->not->toContain('<p>');
});

it('leaves values with no short form, and fields the template lacks, empty', function (): void {
    expect(ShortValue::of($this->course, 'gallery'))->toBe('')
        ->and(ShortValue::of($this->course, 'nope'))->toBe('');
});
