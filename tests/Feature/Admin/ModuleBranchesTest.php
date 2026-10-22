<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Menus\Admin\MenuEditor;

beforeEach(function (): void {
    courseSite();
    lynx()->menus()->create('top', 'Top bar');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Handbook', 'url' => 'https://example.com']);
    test()->actingAs(new GenericUser(['id' => 1]));
});

function menuItemId(): int
{
    return (int) lynx()->pages->find('template=menu-item, include=all')->first()?->id;
}

it('keeps the menus out of the site tree', function (): void {
    Livewire::test(PageTree::class)
        ->assertSee('Courses')
        ->assertDontSee('Top bar')
        ->assertDontSeeHtml('data-page-id="'.lynx()->menus()->root()?->id.'"');
});

it('still shows a menu\'s items in the menu editor\'s tree', function (): void {
    Livewire::test(PageTree::class, ['root' => (string) lynx()->menus()->page('top')?->id])->assertSee('Handbook');
});

it('keeps system pages last, so hiding them leaves the other positions right', function (): void {
    $news = lynx()->pages->new('courses', '/');
    $news->name = 'news';
    $news->title = 'News';
    $news->save();
    lynx()->pages->sort('/courses/', 99);

    expect(lynx()->pages->find('parent=/, include=all, sort=sort')->map(fn ($page): string => (string) $page->template()->name))
        ->toBe(['courses', 'courses', 'menus'])
        ->and(lynx()->pages->get('/courses/')?->sort)->toBe(1);
});

it('sends the editor of a menu page to the menu editor', function (): void {
    $menu = lynx()->menus()->page('top');

    $this->get(route('lynx.admin.pages.edit', lynx()->menus()->root()?->id))->assertRedirect(route('lynx.admin.menus'));
    $this->get(route('lynx.admin.pages.edit', $menu?->id))->assertRedirect(route('lynx.admin.menus', ['menu' => $menu?->id]));
    $this->get(route('lynx.admin.pages.edit', menuItemId()))->assertRedirect(route('lynx.admin.menus', ['menu' => $menu?->id, 'item' => menuItemId()]));
    $this->get(route('lynx.admin.pages.edit', lynx()->pages->get('/courses/')?->id))->assertOk();
});

it('opens the item given in the menu editor\'s URL', function (): void {
    Livewire::withQueryParams(['menu' => lynx()->menus()->page('top')?->id, 'item' => menuItemId()])
        ->test(MenuEditor::class)
        ->assertSet('isEditingItem', true)
        ->assertSee('Handbook');
});
