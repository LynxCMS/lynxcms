<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\DoctorChecks;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Support\Tables;

/*
 * lynx:doctor runs every registered check and reports what it found, each with a fix: a template
 * without a view, a field on no template, orphaned values, schedules the scheduler never ran. Errors make it fail; warnings do not. Modules add their own checks.
 */

beforeEach(function (): void {
    courseSite();
    // The media package's storage link check depends on the machine; its own test covers it.
    config(['filesystems.links' => []]);
    $views = testingPath('doctor-views');
    File::ensureDirectoryExists($views.'/templates');
    File::put($views.'/templates/courses.blade.php', 'Courses');
    File::put($views.'/templates/course.blade.php', 'Course');
    File::put($views.'/templates/simple-page.blade.php', 'Page');
    File::put($views.'/templates/not-found.blade.php', 'Not found');
    View::addLocation($views);
});

afterEach(function (): void {
    File::deleteDirectory(testingPath('doctor-views'));
});

/**
 * The checks' report, by key.
 *
 * @return array<string, array{label: string, findings: list<array{severity: string, message: string, fix: string|null}>}>
 */
function doctorReport(int $exit = 0): array
{
    expect(Artisan::call('lynx:doctor', ['--json' => true]))->toBe($exit);

    return collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->keyBy('key')->all();
}

/** @return list<string> */
function severities(array $report, string $key): array
{
    return array_column($report[$key]['findings'], 'severity');
}

it('finds nothing wrong on a healthy site', function (): void {
    $report = doctorReport();

    expect(array_merge(...array_column($report, 'findings')))->toBe([])
        ->and(array_keys($report))->toContain('template-views', 'unused-fields', 'orphaned-values', 'scheduler');
});

it('warns about a template whose pages have no view', function (): void {
    lynx()->templates->add('event', fields: ['title']);
    lynx()->templates->add('venue', fields: ['title']);
    $event = lynx()->pages->new('event', '/');
    $event->name = 'launch';
    $event->title = 'Launch';
    $event->save();

    $report = doctorReport();

    expect(severities($report, 'template-views'))->toBe(['warning'])
        ->and($report['template-views']['findings'][0]['message'])->toContain('event')
        ->and($report['template-views']['findings'][0]['fix'])->toContain('lynx:make:view event');
});

it('warns about a field on no template', function (): void {
    lynx()->fields->add('subtitle', 'text');

    expect(doctorReport()['unused-fields']['findings'][0]['message'])->toContain('subtitle');
});

it('counts a field inside a repeater\'s items as used', function (): void {
    lynx()->fields->add('caption', 'text');
    lynx()->fields->add('slides', 'repeater', settings: ['fields' => [['name' => 'caption']]]);
    lynx()->templates->update('course', ['add' => ['slides']]);

    expect(doctorReport()['unused-fields']['findings'])->toBe([]);
});

it('warns about values a template no longer shows', function (): void {
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->price = 120;
    $alpha->save();
    lynx()->templates->update('course', ['remove' => ['price']]);

    $report = doctorReport();

    expect(severities($report, 'orphaned-values'))->toBe(['warning'])
        ->and($report['orphaned-values']['findings'][0]['message'])->toContain('price', 'course');
});

it('fails when schedules are overdue, as when the scheduler does not run', function (): void {
    ['alpha' => $alpha] = createCourses('alpha');
    DB::table(Tables::SCHEDULES)->insert(['page_id' => $alpha->id, 'language_id' => lynx()->languages->default()->id, 'action' => 'publish', 'at' => now()->subHour()]);

    expect(severities(doctorReport(exit: 1), 'scheduler'))->toBe(['error']);
});

it('runs the checks modules add', function (): void {
    $check = new class implements Check
    {
        public function key(): string
        {
            return 'module-check';
        }

        public function label(): string
        {
            return 'A module check';
        }

        public function run(): array
        {
            return [new Finding(Severity::Error, 'Something is off.', 'Fix it.')];
        }
    };
    app(DoctorChecks::class)->add($check);

    expect(doctorReport(exit: 1)['module-check']['findings'])->toBe([['severity' => 'error', 'message' => 'Something is off.', 'fix' => 'Fix it.']]);
});

it('prints a readable report', function (): void {
    lynx()->fields->add('subtitle', 'text');

    $this->artisan('lynx:doctor')->assertSuccessful();
});
