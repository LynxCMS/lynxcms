<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\SearchIndexScreen;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\Trash;

/*
 * Settings > Search index (ADR-0120): what the index holds, a page's indexed words, and rebuilding
 * it, for people with search.manage.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true]]]);
    ['alpha' => $this->alpha] = createCourses('alpha');
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'indexer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'search.manage']]]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['indexer']]);
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['writer']]);
});

it('shows each language\'s rows and the fields each template indexes', function (): void {
    $this->actingAs(new GenericUser(['id' => 3]));

    $this->get(route('lynx.admin.search-index'))->assertOk()
        ->assertSeeLivewire(SearchIndexScreen::class)
        ->assertSeeHtml('data-language="pt"')
        ->assertSeeHtml('data-template="course"')
        ->assertSeeHtml('data-own="pt"')
        ->assertSee('summary');
});

it('lists the site\'s templates apart from those of Lynx and its modules', function (): void {
    lynx()->pages->trash(createCourses('gone')['gone']);
    $this->actingAs(new GenericUser(['id' => 3]));
    $system = ['name' => Trash::TEMPLATE, 'owner' => 'Lynx'];

    Livewire::test(SearchIndexScreen::class)
        ->assertSeeHtml('data-site-templates')
        ->assertSeeHtml('data-system-templates')
        ->assertSeeInOrder(['data-site-templates', 'data-template="course"', 'data-system-templates', 'data-template="'.$system['name'].'"', $system['owner']]);
});

it('lists the screen in Settings only for people with search.manage', function (): void {
    $this->actingAs(new GenericUser(['id' => 3]));
    $this->get(route('lynx.admin.home'))->assertSee(route('lynx.admin.search-index'));

    $this->actingAs(new GenericUser(['id' => 5]));
    $this->get(route('lynx.admin.home'))->assertDontSee(route('lynx.admin.search-index'));
});

it('refuses people without search.manage', function (): void {
    $this->actingAs(new GenericUser(['id' => 5]));

    $this->get(route('lynx.admin.search-index'))->assertForbidden();
});

it('shows the words indexed for a page found by path or id', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => ['summary' => ['pt' => 'Dois livros']]]);
    $this->actingAs(new GenericUser(['id' => 3]));

    Livewire::test(SearchIndexScreen::class)
        ->set('page', '/courses/alpha/')
        ->assertSee('alpha dois livros livro')
        ->set('page', (string) $this->alpha->id)
        ->assertSee('alpha dois livros livro')
        ->set('page', '/nowhere/')
        ->assertSee(__('lynx-admin::search_index.lookup.missing'));
});

it('rebuilds the whole index or one template\'s pages', function (): void {
    $this->actingAs(new GenericUser(['id' => 3]));

    Livewire::test(SearchIndexScreen::class)
        ->call('rebuild')
        ->assertDispatched('lynx-notify')
        ->set('template', 'course')
        ->call('rebuild')
        ->assertSee(trans_choice('lynx-admin::search_index.rebuilt', 1, ['count' => 1]));
});
