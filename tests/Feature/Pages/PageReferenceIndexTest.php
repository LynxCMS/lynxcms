<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LynxCms\Core\Actions\Pages\RestoreRevision;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Events\PageReferencesChanged;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\References\PageReferenceIndex;
use LynxCms\Core\Support\Tables;

/*
 * The index of page references (ADR-0145): one row per reference, kept in step by every write.
 */

beforeEach(function (): void {
    courseSite();
    $this->courses = createCourses('alpha', 'beta', 'gamma');
});

/**
 * The page's rows: [linked page, field, item key, sort], in sort order.
 *
 * @return list<array{0: int, 1: string, 2: string|null, 3: int}>
 */
function referenceRows(Page $page): array
{
    return DB::table(Tables::PAGE_REFERENCES)
        ->join(Tables::FIELDS, Tables::FIELDS.'.id', '=', Tables::PAGE_REFERENCES.'.field_id')
        ->where('page_id', $page->id)
        ->orderBy(Tables::FIELDS.'.name')->orderBy('sort')
        ->get(['linked_page_id', Tables::FIELDS.'.name', 'item_key', 'sort'])
        ->map(fn (object $row): array => [(int) $row->linked_page_id, $row->name, $row->item_key, (int) $row->sort])
        ->all();
}

function relate(Page $from, Page ...$targets): void
{
    $from->related = array_map(fn (Page $page): int => (int) $page->id, $targets);
    $from->save();
}

/** Whether a rebuild would change nothing. */
function indexIsCurrent(): bool
{
    return app(PageReferenceIndex::class)->differences() === [];
}

it('indexes the references a save stores, in order', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = $this->courses;

    relate($alpha, $gamma, $beta);

    expect(referenceRows($alpha))->toBe([[(int) $gamma->id, 'related', null, 0], [(int) $beta->id, 'related', null, 1]])
        ->and(DB::table(Tables::PAGE_REFERENCES)->where('page_id', $alpha->id)->whereNull('created_at')->exists())->toBeFalse();
});

it('removes and reorders rows when the references change', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = $this->courses;
    relate($alpha, $gamma, $beta);

    relate($alpha, $beta);

    expect(referenceRows($alpha))->toBe([[(int) $beta->id, 'related', null, 0]])
        ->and(indexIsCurrent())->toBeTrue();
});

it('records the item that holds a reference', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    lynx()->fields->add('partner', 'page', settings: ['multiple' => false]);
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'partner']]]);
    lynx()->templates->update('course', ['add' => ['credits']]);

    $alpha->credits = [['values' => ['partner' => (int) $beta->id]]];
    $alpha->save();
    $key = lynx()->pages->get($alpha->id)->getUnformatted('credits')[0]['key'];

    expect(referenceRows($alpha))->toBe([[(int) $beta->id, 'credits', $key, 0]]);
});

it('lists added and removed references in the save change and queues one event', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = $this->courses;
    relate($alpha, $gamma);
    Event::fake([PageReferencesChanged::class]);

    $result = lynx()->run(SavePage::class, ['id' => (int) $alpha->id, 'values' => ['related' => [(int) $beta->id]]]);
    $references = $result->changes->all()[0]->data['references'];

    expect($references['added'])->toBe([['linked_page_id' => (int) $beta->id, 'field' => 'related', 'item_key' => null]])
        ->and($references['removed'])->toBe([['linked_page_id' => (int) $gamma->id, 'field' => 'related', 'item_key' => null]]);
    Event::assertDispatchedTimes(PageReferencesChanged::class, 1);
    Event::assertDispatched(PageReferencesChanged::class, fn (PageReferencesChanged $event): bool => $event->pageId === (int) $alpha->id && count($event->added) === 1 && count($event->removed) === 1);
});

it('queues no event when the references stay the same', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    relate($alpha, $beta);
    Event::fake([PageReferencesChanged::class]);

    $result = lynx()->run(SavePage::class, ['id' => (int) $alpha->id, 'values' => ['price' => 10]]);

    expect($result->changes->all()[0]->data)->not->toHaveKey('references');
    Event::assertNotDispatched(PageReferencesChanged::class);
});

it('writes nothing on a dry run', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;

    $result = lynx()->run(SavePage::class, ['id' => (int) $alpha->id, 'values' => ['related' => [(int) $beta->id]]], dryRun: true);

    expect($result->changes->all()[0]->data['references']['added'])->toHaveCount(1)
        ->and(referenceRows($alpha))->toBe([]);
});

it('leaves drafts out until they are published', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;

    lynx()->pages->saveDraft($alpha, ['related' => [(int) $beta->id]]);
    $draftRows = DB::table(Tables::PAGE_REFERENCES)->where('linked_page_id', $beta->id)->count();
    lynx()->pages->publishDraft($alpha);

    expect($draftRows)->toBe(0)
        ->and(referenceRows($alpha))->toBe([[(int) $beta->id, 'related', null, 0]]);
});

it('follows the fields the template uses', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    relate($alpha, $beta);

    lynx()->templates->update('course', ['remove' => ['related']]);
    $removed = referenceRows($alpha);
    lynx()->templates->update('course', ['add' => ['related']]);

    expect($removed)->toBe([])
        ->and(referenceRows($alpha))->toBe([[(int) $beta->id, 'related', null, 0]])
        ->and(lynx()->pages->backlinks($beta)->count())->toBe(1);
});

it('drops the rows of fields the new template does not use when switching', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    relate($alpha, $beta);

    lynx()->pages->switchTemplate($alpha, 'courses');

    expect(referenceRows($alpha))->toBe([])
        ->and(indexIsCurrent())->toBeTrue();
});

it('drops the rows of deleted pages and fields', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = $this->courses;
    relate($alpha, $beta);
    relate($gamma, $beta);

    lynx()->pages->delete($alpha);
    $afterDelete = DB::table(Tables::PAGE_REFERENCES)->where('linked_page_id', $beta->id)->count();
    lynx()->templates->update('course', ['remove' => ['related']]);
    lynx()->fields->delete('related');

    expect($afterDelete)->toBe(1)
        ->and(DB::table(Tables::PAGE_REFERENCES)->count())->toBe(0);
});

it('keeps the index current through revisions and duplicates', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = $this->courses;
    relate($alpha, $beta);
    relate($alpha, $gamma);

    $revisions = lynx()->pages->revisions($alpha);
    lynx()->run(RestoreRevision::class, ['page' => (int) $alpha->id, 'revision' => $revisions[1]['id']]);
    lynx()->pages->duplicate($alpha);

    expect(indexIsCurrent())->toBeTrue()
        ->and(referenceRows($alpha))->toBe([[(int) $beta->id, 'related', null, 0]])
        ->and(DB::table(Tables::PAGE_REFERENCES)->where('linked_page_id', $beta->id)->count())->toBe(2);
});

it('finds backlinks through the index', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    relate($alpha, $beta);

    DB::table(Tables::PAGE_REFERENCES)->delete();

    expect(lynx()->pages->backlinks($beta)->count())->toBe(0);
});

it('fills a new index from the stored values', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    relate($alpha, $beta);
    DB::table(Tables::PAGE_REFERENCES)->delete();

    app(PageReferenceIndex::class)->rebuild();

    expect(referenceRows($alpha))->toBe([[(int) $beta->id, 'related', null, 0]])
        ->and(DB::table(Tables::PAGE_REFERENCES)->whereNotNull('created_at')->exists())->toBeFalse()
        ->and(lynx()->run(SavePage::class, ['id' => (int) $alpha->id, 'values' => ['price' => 5]]))->toBeInstanceOf(ActionResult::class);
});
