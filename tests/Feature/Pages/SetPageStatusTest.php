<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Pages\SetPageStatus;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(fn (): Lynx => courseSite());

function statusOf(Page $page): int
{
    return (int) DB::table('lynx_pages')->where('id', $page->id)->value('status');
}

it('adds and removes status flags without touching anything else', function (): void {
    $page = createCourses('alpha')['alpha'];

    $updated = lynx()->pages->setStatus($page, add: ['hidden', 'locked']);
    expect(statusOf($page))->toBe(PageStatus::HIDDEN | PageStatus::LOCKED)
        ->and($updated)->toBeInstanceOf(Page::class);

    // A locked page only lets the lock change (ADR-0027).
    lynx()->pages->setStatus($page, remove: ['locked']);
    expect(statusOf($page))->toBe(PageStatus::HIDDEN);
});

it('sets the publish date when a page is published', function (): void {
    $page = createCourses('alpha')['alpha'];
    lynx()->pages->setStatus($page, add: ['unpublished']);
    DB::table('lynx_pages')->where('id', $page->id)->update(['published_at' => null]);

    lynx()->pages->setStatus($page, remove: ['unpublished']);

    expect(DB::table('lynx_pages')->where('id', $page->id)->value('published_at'))->not->toBeNull();
});

it('does not require valid field values', function (): void {
    $page = createCourses('alpha')['alpha'];
    DB::table('lynx_f_title')->where('page_id', $page->id)->delete();

    lynx()->pages->setStatus($page, add: ['hidden']);

    expect(statusOf($page))->toBe(PageStatus::HIDDEN);
});

it('rejects flags that are not editable', function (string $flag): void {
    $page = createCourses('alpha')['alpha'];

    expect(fn () => lynx()->pages->setStatus($page, add: [$flag]))->toThrow(ValidationException::class);
})->with(['system', 'bogus']);

it('rejects a call without changes', function (): void {
    $page = createCourses('alpha')['alpha'];

    expect(fn () => lynx()->pages->setStatus($page))->toThrow(ValidationException::class);
});

it('reports changes on a dry run and writes nothing', function (): void {
    $page = createCourses('alpha')['alpha'];

    $result = lynx()->pages->setStatus($page, add: ['hidden'], dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and($result->changes->count())->toBe(1)
        ->and(statusOf($page))->toBe(0);
});

it('rolls back when a hook halts the action', function (): void {
    $page = createCourses('alpha')['alpha'];
    lynx()->hook(SetPageStatus::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn () => lynx()->pages->setStatus($page, add: ['hidden']))->toThrow(ActionHalted::class)
        ->and(statusOf($page))->toBe(0);
});
