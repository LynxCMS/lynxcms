<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use LynxCms\Admin\Editor\FieldAddress;
use LynxCms\Admin\Livewire\PageReferencesScreen;
use LynxCms\Admin\References\RebuildIndex;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\References\ReferenceReports;
use LynxCms\Core\Support\Tables;

/*
 * Settings > Page references (ADR-0145): reports on the links between pages, and the index's health.
 */

beforeEach(function (): void {
    courseSite();
    DB::table('users')->insert(['id' => 1, 'name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => 'x']);
    $this->actingAs(new GenericUser(['id' => 1]));
    ['alpha' => $this->alpha, 'beta' => $this->beta, 'gamma' => $this->gamma] = createCourses('alpha', 'beta', 'gamma');
});

function linkPage(Page $from, Page ...$to): void
{
    $from->related = array_map(fn (Page $page): int => (int) $page->id, $to);
    $from->save();
}

/** @return array<int, string> referencing page id => reason */
function brokenReasons(): array
{
    $reasons = [];

    foreach (app(ReferenceReports::class)->broken(null, 50, 0)['rows'] as $row) {
        $reasons[$row['reference']->linkedPageId] = $row['reason'];
    }

    return $reasons;
}

it('opens from the admin', function (): void {
    $this->get(route('lynx.admin.page-references'))->assertOk()->assertSeeLivewire(PageReferencesScreen::class);
});

it('finds references to deleted, trashed and unpublished pages', function (): void {
    $delta = createCourses('delta')['delta'];
    linkPage($this->alpha, $this->beta, $this->gamma, $delta);
    lynx()->pages->trash($this->beta);
    lynx()->pages->setStatus($this->gamma, add: ['unpublished']);
    DB::table(Tables::PAGES)->where('id', $delta->id)->delete();

    expect(brokenReasons())->toBe([
        (int) $this->beta->id => ReferenceReports::TRASHED,
        (int) $this->gamma->id => ReferenceReports::UNPUBLISHED,
        (int) $delta->id => ReferenceReports::MISSING,
    ]);
});

it('lists broken links with a link to the field that holds each', function (): void {
    linkPage($this->alpha, $this->beta);
    lynx()->pages->trash($this->beta);

    Livewire::test(PageReferencesScreen::class)
        ->assertSeeHtml('data-report-page="'.$this->alpha->id.'"')
        ->assertSeeHtml('href="'.e(FieldAddress::url($this->alpha, 'related')).'"');
});

it('lists the pages nothing links to', function (): void {
    linkPage($this->alpha, $this->beta);

    Livewire::test(PageReferencesScreen::class)
        ->call('show', 'unlinked')
        ->assertSet('report', 'unlinked')
        ->assertSeeHtml('data-report-page="'.$this->gamma->id.'"')
        ->assertDontSeeHtml('data-report-page="'.$this->beta->id.'"');
});

it('pages through the pages nothing links to', function (): void {
    $first = app(ReferenceReports::class)->unlinked('include=all, template=course', null, 1, 0);
    $second = app(ReferenceReports::class)->unlinked('include=all, template=course', null, 1, 1);

    expect($first['total'])->toBe(3)
        ->and($first['ids'])->toHaveCount(1)
        ->and($second['ids'])->not->toBe($first['ids']);
});

it('lists the most linked pages first', function (): void {
    linkPage($this->alpha, $this->gamma);
    linkPage($this->beta, $this->gamma, $this->alpha);

    expect(app(ReferenceReports::class)->mostLinked(null, 20))->toBe([
        ['id' => (int) $this->gamma->id, 'count' => 2],
        ['id' => (int) $this->alpha->id, 'count' => 1],
    ]);
    Livewire::test(PageReferencesScreen::class)->call('show', 'linked')->assertSeeHtml('data-report-page="'.$this->gamma->id.'"');
});

it('lists only references from pages the user may view', function (): void {
    linkPage($this->alpha, $this->gamma);
    linkPage($this->beta, $this->gamma);
    lynx()->pages->trash($this->gamma);
    lynx()->run(SaveRole::class, ['name' => 'alpha-only', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view', 'branches' => [$this->alpha->id]]]]);
    DB::table('users')->insert(['id' => 8, 'name' => 'Rui', 'email' => 'rui@example.com', 'password' => 'x']);
    lynx()->run(AssignRoles::class, ['user' => '8', 'roles' => ['alpha-only']]);
    $this->actingAs(new GenericUser(['id' => 8]));

    Livewire::test(PageReferencesScreen::class)
        ->assertSeeHtml('data-report-page="'.$this->alpha->id.'"')
        ->assertDontSeeHtml('data-report-page="'.$this->beta->id.'"')
        ->assertDontSeeHtml('data-index-health')
        ->call('checkIndex')
        ->assertForbidden();
});

it('checks the index and queues a rebuild', function (): void {
    linkPage($this->alpha, $this->beta);
    Queue::fake();

    $screen = Livewire::test(PageReferencesScreen::class)->call('checkIndex')->assertSeeHtml('data-index-check="current"');
    DB::table(Tables::PAGE_REFERENCES)->delete();
    $screen->call('checkIndex')->assertSeeHtml('data-index-check="differs"')->call('rebuild');

    Queue::assertPushed(RebuildIndex::class);
});

it('rebuilds the index from the queued job', function (): void {
    linkPage($this->alpha, $this->beta);
    DB::table(Tables::PAGE_REFERENCES)->delete();

    new RebuildIndex(1)->handle();

    expect(DB::table(Tables::PAGE_REFERENCES)->where('linked_page_id', $this->beta->id)->count())->toBe(1);
});
