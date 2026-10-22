<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use LynxCms\Core\Actions\Pages\SetPageStatus;
use LynxCms\Core\Actions\Pages\SwitchTemplate;
use LynxCms\Core\Actions\Strings\SaveStrings;
use LynxCms\Core\Events\ContentChanged;
use LynxCms\Core\Events\PageSaved;
use LynxCms\Core\Events\PageStatusChanged;
use LynxCms\Core\Events\PageTemplateSwitched;
use LynxCms\Core\Events\SiteStringsSaved;
use Symfony\Component\Finder\Finder;

beforeEach(function (): void {
    courseSite();
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alpha = (int) $alpha->id;
});

it('dispatches a status change with the flags added and removed', function (): void {
    Event::fake([PageStatusChanged::class]);

    lynx()->run(SetPageStatus::class, ['page' => $this->alpha, 'add' => ['unpublished', 'hidden']]);

    Event::assertDispatched(PageStatusChanged::class, fn (PageStatusChanged $event): bool => $event->pageId === $this->alpha
        && collect($event->added)->sort()->values()->all() === ['hidden', 'unpublished'] && $event->removed === [] && $event->affectedPages() === [$this->alpha]);
});

it('dispatches a template switch', function (): void {
    Event::fake([PageTemplateSwitched::class]);
    lynx()->templates->add('lesson', fields: ['title']);

    lynx()->run(SwitchTemplate::class, ['page' => $this->alpha, 'template' => 'lesson']);

    Event::assertDispatched(PageTemplateSwitched::class, fn (PageTemplateSwitched $event): bool => $event->pageId === $this->alpha
        && $event->from === 'course' && $event->to === 'lesson');
});

it('marks site texts as changing every page', function (): void {
    Event::fake([SiteStringsSaved::class]);
    View::addLocation(__DIR__.'/../../Fixtures/views');
    lynx()->templates->add('landing', fields: ['title']);
    $page = lynx()->pages->new('landing', '/');
    $page->name = 'landing';
    $page->title = 'Landing';
    $page->save();

    lynx()->run(SaveStrings::class, ['page' => $page->id, 'language' => 'pt', 'values' => ['footer.note' => 'Todos os direitos']]);

    Event::assertDispatched(SiteStringsSaved::class, fn (SiteStringsSaved $event): bool => $event->keys === ['footer.note']
        && $event->language === 'pt' && $event->affectedPages() === null);
});

it('lets one listener on ContentChanged hear every content event', function (): void {
    $heard = [];
    Event::listen(ContentChanged::class, function (ContentChanged $event) use (&$heard): void {
        $heard[] = [$event::class, $event->affectedPages()];
    });

    lynx()->run(SetPageStatus::class, ['page' => $this->alpha, 'add' => ['hidden']]);
    $page = lynx()->pages->get($this->alpha);
    $page->title = 'Alpha, again';
    $page->save();

    expect($heard)->toBe([[PageStatusChanged::class, [$this->alpha]], [PageSaved::class, [$this->alpha]]]);
});

it('marks every page and language event as a content change', function (): void {
    $events = collect(Finder::create()->files()->in(__DIR__.'/../../../packages/core/src/Events')->name('*.php'))
        ->map(fn ($file): string => 'LynxCms\\Core\\Events\\'.$file->getBasename('.php'))
        ->reject(fn (string $class): bool => $class === ContentChanged::class);

    expect($events->reject(fn (string $class): bool => is_subclass_of($class, ContentChanged::class))->values()->all())->toBe([]);
});
