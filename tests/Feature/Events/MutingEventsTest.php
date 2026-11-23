<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Actions\Content\ImportContent;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Events\ContentBatchChanged;
use LynxCms\Core\Events\PageMoved;
use LynxCms\Core\Events\PageSaved;
use LynxCms\Core\Pages\Page;

/*
 * lynx()->withoutEvents() (ADR-0145): mutes events while a callback runs. Muted content changes
 * fold into one ContentBatchChanged, and the work that keeps Lynx's stored data correct still runs.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
    Event::fake([PageSaved::class, PageMoved::class, ContentBatchChanged::class]);
});

function quietRetitle(Page $page, string $title): void
{
    lynx()->run(SavePage::class, ['id' => (int) $page->id, 'values' => ['title' => $title]]);
}

it('mutes every event while the callback runs and returns its value', function (): void {
    $value = lynx()->withoutEvents(function (): string {
        quietRetitle($this->alpha, 'Quiet');

        return 'done';
    });

    expect($value)->toBe('done');
    Event::assertNotDispatched(PageSaved::class);
});

it('mutes only the listed events', function (): void {
    lynx()->withoutEvents(function (): void {
        quietRetitle($this->alpha, 'Quiet');
        lynx()->pages->move($this->beta, '/courses/alpha/');
    }, [PageSaved::class]);

    Event::assertNotDispatched(PageSaved::class);
    Event::assertDispatched(PageMoved::class);
});

it('folds muted content changes into one batch event after the callback', function (): void {
    lynx()->withoutEvents(function (): void {
        quietRetitle($this->alpha, 'One');
        quietRetitle($this->beta, 'Two');
        quietRetitle($this->alpha, 'Three');
    });

    Event::assertDispatchedTimes(ContentBatchChanged::class, 1);
    Event::assertDispatched(ContentBatchChanged::class, fn (ContentBatchChanged $event): bool => $event->affectedPages() === [(int) $this->alpha->id, (int) $this->beta->id]);
});

it('dispatches events again once the callback ends, also on an exception', function (): void {
    expect(fn (): mixed => lynx()->withoutEvents(fn (): never => throw new RuntimeException('Stop.')))->toThrow(RuntimeException::class);

    quietRetitle($this->alpha, 'Loud');

    Event::assertDispatched(PageSaved::class);
});

it('keeps muting until the outer callback ends when nested', function (): void {
    lynx()->withoutEvents(function (): void {
        lynx()->withoutEvents(fn () => quietRetitle($this->alpha, 'Inner'), [PageSaved::class]);
        quietRetitle($this->beta, 'Outer');
    });

    Event::assertNotDispatched(PageSaved::class);
    Event::assertDispatchedTimes(ContentBatchChanged::class, 1);
});

it('folds nothing from a dry run', function (): void {
    lynx()->withoutEvents(fn (): mixed => lynx()->run(SavePage::class, ['id' => (int) $this->alpha->id, 'values' => ['title' => 'Draft']], dryRun: true));

    Event::assertNotDispatched(ContentBatchChanged::class);
});

it('keeps the search index current while events are muted', function (): void {
    lynx()->withoutEvents(fn () => quietRetitle($this->alpha, 'Hydraulics'));

    expect(lynx()->pages->find('search*=hydraulics')->count())->toBe(1);
});

it('imports without events when asked', function (): void {
    $dir = sys_get_temp_dir().'/lynx-muted-'.uniqid();
    app(ContentExporter::class)->export($dir);
    lynx()->pages->delete('/courses/');

    lynx()->run(ImportContent::class, ['path' => $dir, 'events' => false]);
    File::deleteDirectory($dir);

    Event::assertNotDispatched(PageSaved::class);
    Event::assertDispatchedTimes(ContentBatchChanged::class, 1);
    expect(lynx()->pages->get('/courses/alpha/'))->not->toBeNull();
});
