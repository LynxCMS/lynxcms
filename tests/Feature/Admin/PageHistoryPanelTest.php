<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageHistory;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Tables;

beforeEach(function (): void {
    courseSite();
    DB::table('users')->insert(['id' => 1, 'name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => 'x']);
    $this->actingAs(new GenericUser(['id' => 1]));
});

function historyPage(string $name = 'alpha'): Page
{
    return createCourses($name)[$name];
}

function historyFor(Page $page): Testable
{
    return Livewire::test(PageHistory::class, ['pageId' => $page->id]);
}

function renameTo(Page $page, string $name): void
{
    $page = lynx()->pages->get($page->id);
    $page->name = $name;
    $page->save();
}

it('shows redirects, backlinks and activity in the Settings tab', function (): void {
    Livewire::test(PageSettings::class, ['pageId' => historyPage()->id])->assertSeeLivewire(PageHistory::class);
});

it('lists old URLs and whether they still redirect', function (): void {
    $alpha = historyPage();
    renameTo($alpha, 'alpha-2');
    renameTo($alpha, 'alpha-3');
    historyPage('alpha');

    historyFor($alpha)
        ->assertSeeInOrder(['/courses/alpha-2', 'Redirects', '/courses/alpha', 'Taken by another page'])
        ->assertDontSee('No old URLs.');
});

it('lists the pages that link to the page with what they are', function (): void {
    $alpha = historyPage();
    $beta = historyPage('beta');
    $beta->related = [$alpha->id];
    $beta->save();

    historyFor($alpha)
        ->assertSee('Beta')
        ->assertSeeHtml('href="'.route('lynx.admin.pages.edit', $beta->id).'"')
        ->assertSee('Course under Courses');
});

it('leaves out backlinks from menu items', function (): void {
    $alpha = historyPage();
    lynx()->menus()->create('top', 'Top bar');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => $alpha->id]);

    historyFor($alpha)->assertSee('No pages link here.')->assertDontSee('Menu item');
});

it('deletes an old URL', function (): void {
    $alpha = historyPage();
    renameTo($alpha, 'alpha-2');

    historyFor($alpha)
        ->assertSee('/courses/alpha')
        ->call('deleteRedirect', 'default', 'courses/alpha')
        ->assertHasNoErrors()
        ->assertSee('No old URLs.')
        ->assertSee('Delete old URL /courses/alpha of page alpha-2');
});

it('shows why an old URL could not be deleted', function (): void {
    historyFor(historyPage())->call('deleteRedirect', 'default', 'courses/nope')->assertHasErrors('path')->assertSee('has no old URL');
});

it('offers no Delete on a locked page', function (): void {
    $alpha = historyPage();
    renameTo($alpha, 'alpha-2');
    lynx()->pages->setStatus($alpha->id, add: ['locked']);

    historyFor($alpha)->assertSee('/courses/alpha')->assertDontSeeHtml('wire:click="deleteRedirect(');
});

it('lists the activity with who did what', function (): void {
    $alpha = historyPage();
    lynx()->pages->setStatus($alpha->id, add: ['hidden']);
    lynx()->actingAs(Actor::mcp(1), fn (): Page => lynx()->pages->setStatus($alpha->id, add: ['locked']));
    lynx()->actingAs(Actor::ai(), fn (): Page => lynx()->pages->setStatus($alpha->id, remove: ['locked']));

    historyFor($alpha)->assertSeeInOrder([
        'AI agent', 'Set status of page alpha to [hidden]',
        'Ana Lima via MCP', 'Set status of page alpha to [hidden, locked]',
        'Ana Lima', 'Set status of page alpha to [hidden]',
        'Create course page alpha',
    ]);
});

it('says when there is nothing to list yet', function (): void {
    $page = historyPage();
    DB::table(Tables::ACTIVITY)->delete();

    historyFor($page)->assertSee(['No old URLs.', 'No pages link here.', 'No activity yet.']);
});

it('shows more activity on request', function (): void {
    $alpha = historyPage();
    foreach (range(1, PageHistory::ACTIVITY_PER_PAGE) as $i) {
        renameTo($alpha, "alpha-{$i}");
    }

    historyFor($alpha)
        ->assertDontSee('Create course page alpha')
        ->assertSee('Show more')
        ->call('showMoreActivity')
        ->assertSee('Create course page alpha')
        ->assertDontSee('Show more');
});

it('refreshes when the settings change the page', function (): void {
    $alpha = historyPage();
    $history = historyFor($alpha)->assertSee('No old URLs.');

    renameTo($alpha, 'alpha-2');

    $history->dispatch('lynx-page-changed', revision: 2)->assertSee('/courses/alpha');
});
