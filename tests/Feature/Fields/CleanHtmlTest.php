<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Fields\CleanHtmlValues;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Lynx;
use LynxCms\Core\Support\Tables;

beforeEach(fn (): Lynx => courseSite());

const DIRTY_HTML = '<p onclick="steal()">Hi<script>alert(1)</script></p>';

/** Stores values the way old versions did, without the sanitizer. */
function storedBeforeSanitizer(): int
{
    $alpha = createCourses('alpha')['alpha'];
    $languages = lynx()->languages->all()->pluck('id', 'code');

    foreach (['body' => DIRTY_HTML, 'summary' => '<script>kept</script>'] as $field => $html) {
        DB::table(Tables::field($field))->insert([
            ['page_id' => $alpha->id, 'language_id' => $languages['default'], 'data' => $html],
            ['page_id' => $alpha->id, 'language_id' => $languages['pt'], 'data' => $html],
        ]);
    }

    return (int) $alpha->id;
}

/** @return list<string> */
function storedValues(string $field): array
{
    return DB::table(Tables::field($field))->orderBy('language_id')->pluck('data')->all();
}

it('cleans stored values of html fields the way a save would', function (): void {
    storedBeforeSanitizer();

    $cleaned = lynx()->run(CleanHtmlValues::class)->value;

    expect($cleaned)->toBe(['body' => 2])
        ->and(storedValues('body'))->toBe(['<p>Hi</p>', '<p>Hi</p>'])
        ->and(storedValues('summary'))->toBe(['<script>kept</script>', '<script>kept</script>']);
});

it('reports a destructive change on a dry run and keeps the values', function (): void {
    storedBeforeSanitizer();

    $result = lynx()->run(CleanHtmlValues::class, dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and($result->changes->isDestructive())->toBeTrue()
        ->and($result->changes->summary()[0])->toContain('Clean 2 value(s) of body')
        ->and(storedValues('body'))->toBe([DIRTY_HTML, DIRTY_HTML]);
});

it('changes nothing when every value is already clean', function (): void {
    storedBeforeSanitizer();
    lynx()->run(CleanHtmlValues::class);

    expect(lynx()->run(CleanHtmlValues::class)->value)->toBe([]);
});

it('cleans one field when asked, and refuses fields without html', function (): void {
    storedBeforeSanitizer();

    expect(lynx()->run(CleanHtmlValues::class, ['field' => 'body'])->value)->toBe(['body' => 2])
        ->and(fn (): ActionResult => lynx()->run(CleanHtmlValues::class, ['field' => 'summary']))->toThrow(ValidationException::class, 'not an html field');
});

it('runs from the command line, dry run first', function (): void {
    storedBeforeSanitizer();

    $this->artisan('lynx:clean-html', ['--dry-run' => true])->expectsOutputToContain('Clean 2 value(s) of body')->assertSuccessful();
    expect(storedValues('body'))->toBe([DIRTY_HTML, DIRTY_HTML]);

    $this->artisan('lynx:clean-html')->expectsOutputToContain('Cleaned 2 value(s)')->assertSuccessful();
    expect(storedValues('body'))->toBe(['<p>Hi</p>', '<p>Hi</p>']);
});
