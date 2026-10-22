<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Lynx;

beforeEach(fn (): Lynx => courseSite());

function revisionOf(string $path): int
{
    return (int) DB::table('lynx_pages')->where('id', lynx()->pages->get($path)->id)->value('revision');
}

function saveTitle(string $path, string $title, ?int $expected = null): void
{
    lynx()->run(SavePage::class, array_filter([
        'id' => lynx()->pages->get($path)->id,
        'values' => ['title' => $title],
        'expected_revision' => $expected,
    ], fn (int|array|null $v): bool => $v !== null));
}

it('counts revisions on every write to a page', function (): void {
    createCourses('alpha', 'beta');
    expect(revisionOf('/courses/alpha/'))->toBe(0);

    saveTitle('/courses/alpha/', 'Alpha 2');
    lynx()->pages->setStatus('/courses/alpha/', add: ['hidden']);
    lynx()->pages->move('/courses/alpha/', '/');
    lynx()->pages->switchTemplate('/alpha/', 'courses');

    expect(revisionOf('/alpha/'))->toBe(4)
        ->and(lynx()->pages->get('/alpha/')->revision)->toBe(4);
});

it('does not count sorting among siblings as a change to the page', function (): void {
    createCourses('alpha', 'beta');

    lynx()->pages->sort('/courses/beta/', 0);

    expect(revisionOf('/courses/beta/'))->toBe(0);
});

it('saves when the expected revision is current', function (): void {
    createCourses('alpha');

    saveTitle('/courses/alpha/', 'Alpha 2', expected: 0);

    expect((string) lynx()->pages->get('/courses/alpha/')->title)->toBe('Alpha 2')
        ->and(revisionOf('/courses/alpha/'))->toBe(1);
});

it('refuses a save based on an older revision and writes nothing', function (): void {
    createCourses('alpha');
    saveTitle('/courses/alpha/', 'Saved by someone else');

    try {
        saveTitle('/courses/alpha/', 'My stale edit', expected: 0);
        $this->fail('Expected a conflict.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('conflict')
            ->and($e->errors()['conflict'][0])->toContain('was saved by')->toContain('after you loaded it');
    }

    expect((string) lynx()->pages->get('/courses/alpha/')->title)->toBe('Saved by someone else')
        ->and(revisionOf('/courses/alpha/'))->toBe(1);
});

it('does not check revisions when none is expected', function (): void {
    createCourses('alpha');
    saveTitle('/courses/alpha/', 'Once');

    saveTitle('/courses/alpha/', 'Twice');

    expect((string) lynx()->pages->get('/courses/alpha/')->title)->toBe('Twice');
});

it('tells agents the revision to send back', function (): void {
    createCourses('alpha');
    saveTitle('/courses/alpha/', 'Alpha 2');

    expect(app(AgentToolkit::class)->getPage('/courses/alpha/')['revision'] ?? null)->toBe(1);
});
