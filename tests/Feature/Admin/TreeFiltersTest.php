<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Admin\Tree\TreeFilters;

/*
 * Filters on the page tree's Details view: pages with a draft, pages with a schedule, and pages
 * not published in each other language. A filter lists the matching pages flat, with where each
 * sits, and pages through them like a branch.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->courses = createCourses('alpha', 'beta', 'gamma');
    lynx()->pages->saveDraft($this->courses['alpha'], ['title' => 'Alpha again']);
    lynx()->pages->schedule($this->courses['beta'], 'default', unpublishAt: now()->addDay());
    $this->courses['gamma']->setLanguageName('pt', 'traduzido')->save();
});

function detailedTree(): Testable
{
    return Livewire::test(PageTree::class)->call('showDetails', true);
}

it('offers the filters with their counts in the Details view only', function (): void {
    Livewire::test(PageTree::class)->assertDontSeeHtml('data-tree-filter=');

    detailedTree()
        ->assertSeeHtml('data-tree-filter="drafts" data-filter-count="1"')
        ->assertSeeHtml('data-tree-filter="scheduled" data-filter-count="1"')
        ->assertSeeHtml('data-tree-filter="missing-pt"');
});

it('lists the pages with a draft, flat', function (): void {
    detailedTree()
        ->call('filterBy', 'drafts')
        ->assertSeeHtml('data-tree-filtered')
        ->assertSee('Alpha')
        ->assertDontSee('Beta')
        ->assertSeeHtml('data-tree-location');
});

it('lists the pages not published in a language', function (): void {
    detailedTree()
        ->call('filterBy', 'missing-pt')
        ->assertSee('Beta')
        ->assertDontSee('Gamma');
});

it('leaves the trash out of the filters', function (): void {
    lynx()->pages->trash($this->courses['alpha']);

    detailedTree()->assertSeeHtml('data-tree-filter="drafts" data-filter-count="0"');
});

it('goes back to the tree for all pages, and refuses filters it does not know', function (): void {
    detailedTree()
        ->call('filterBy', 'drafts')
        ->call('filterBy', '')
        ->assertDontSeeHtml('data-tree-filtered')
        ->call('filterBy', 'missing-xx')
        ->assertSet('filter', '');
});

it('shows the tree again in the simple view', function (): void {
    detailedTree()
        ->call('filterBy', 'drafts')
        ->call('showDetails', false)
        ->assertDontSeeHtml('data-tree-filtered');
});

it('counts pages without a URL among drafts and schedules, but never as missing a language', function (): void {
    lynx()->templates->add('snippet', fields: [['name' => 'title', 'required' => true]], hasUrl: false);
    $snippet = lynx()->pages->new('snippet', '/');
    $snippet->name = 'footer-note';
    $snippet->title = 'Footer note';
    $snippet->save();
    lynx()->pages->saveDraft($snippet, ['title' => 'Footer note, again']);

    $filters = new TreeFilters;
    $found = fn (string $key): array => lynx()->pages->find((string) $filters->selector($key))->ids();

    expect($found(TreeFilters::DRAFTS))->toContain((int) $snippet->id)
        ->and($found(TreeFilters::MISSING.'pt'))->not->toContain((int) $snippet->id);
});
