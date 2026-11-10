<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

/*
 * Keyboard shortcuts (shortcuts.js) read the page: "g" then a letter follows the sidebar link that
 * carries it, "/" focuses the search, "[" and "]" step through the editor's tabs, and "?" opens the
 * list of shortcuts, which names only the screens the user may open.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('marks the sidebar links with their shortcut', function (): void {
    $this->get('/lynx')->assertOk()
        ->assertSee('data-shortcut="g h"', false)
        ->assertSee('data-shortcut="g p"', false)
        ->assertSee('data-shortcut="g f"', false);
});

it('marks the search and lists the shortcuts on every screen', function (): void {
    $this->get('/lynx/pages')->assertOk()
        ->assertSee('data-shortcut-search', false)
        ->assertSee('data-shortcuts', false)
        ->assertSee('data-shortcuts-open', false);
});

it('marks the page editor\'s tabs', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => (int) createCourses('alpha')['alpha']->id])
        ->assertSeeHtml('data-shortcut-tabs');
});

it('lists only the screens the user may open', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['editor']]);
    $this->actingAs(new GenericUser(['id' => 5]));

    $this->get('/lynx')->assertOk()
        ->assertSee('data-shortcut="g p"', false)
        ->assertDontSee('data-shortcut="g f"', false);
});

it('keeps a shortcut given to a navigation item', function (): void {
    $navigation = (new Navigation)->add('Reports', 'lynx.admin.home', shortcut: 'r');

    expect($navigation->items()[0]['shortcut'])->toBe('r');
});
