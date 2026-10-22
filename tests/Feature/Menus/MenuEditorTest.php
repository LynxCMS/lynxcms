<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Menus\Admin\MenuEditor;
use LynxCms\Menus\Admin\MenuItemPresenter;
use LynxCms\Menus\Reading\MenuBuilds;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    $this->actingAs(new GenericUser(['id' => 1]));
});

function topMenuId(): int
{
    return (int) lynx()->menus()->page('top')?->id;
}

it('adds a Menus screen to the admin', function (): void {
    $this->get('/lynx/menus')->assertOk()->assertSeeInOrder(['Pages', 'Menus'])->assertSeeLivewire(MenuEditor::class);
});

it('creates a menu and selects it', function (): void {
    Livewire::test(MenuEditor::class)
        ->set('newMenu.name', 'top')
        ->set('newMenu.title.default', 'Top bar')
        ->set('newMenu.title.pt', 'Barra')
        ->call('createMenu')
        ->assertHasNoErrors()
        ->assertSet('menuId', fn (?int $id): bool => $id === topMenuId())
        ->assertSee('Top bar');

    expect(lynx()->language('pt', fn () => lynx()->menus()->get('top')?->title))->toBe('Barra');
});

it('shows validation errors when creating a menu', function (): void {
    Livewire::test(MenuEditor::class)
        ->set('newMenu.name', 'Not a slug')
        ->set('newMenu.title.default', 'X')
        ->call('createMenu')
        ->assertHasErrors('name');
});

it('adds a section label from the New event', function (): void {
    lynx()->menus()->create('top', 'Top');

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->dispatch('menu-item-new', parent: topMenuId())
        ->assertSet('isEditingItem', true)
        ->set('item.kind', 'section')
        ->set('item.label.default', 'Resources')
        ->set('item.label.pt', 'Recursos')
        ->call('saveItem')
        ->assertHasNoErrors()
        ->assertSet('isEditingItem', false)
        ->assertDispatched('lynx-tree-refresh');

    expect(lynx()->menus()->get('top')->items[0]->label)->toBe('Resources');
});

it('finds pages and adds a page item with its children', function (): void {
    lynx()->menus()->create('top', 'Top');

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->call('newItem', topMenuId())
        ->set('item.kind', 'page')
        ->set('pageSearch', 'cour')
        ->assertSee('Courses')
        ->call('choosePage', (int) lynx()->pages->get('/courses')->id)
        ->assertSet('item.pageTitle', 'Courses')
        ->set('item.children', true)
        ->call('saveItem')
        ->assertHasNoErrors();

    expect(lynx()->menus()->get('top')->items[0]->children[0]->label)->toBe('Alpha');
});

it('finds pages by the text of their searchable fields', function (): void {
    lynx()->menus()->create('top', 'Top');
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true]]]);
    $alpha = lynx()->pages->get('/courses/alpha');
    $alpha->summary = 'Seasons in the mountains';
    $alpha->save();

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->call('newItem', topMenuId())
        ->set('item.kind', 'page')
        ->set('pageSearch', 'mountains')
        ->assertSee('Alpha');
});

it('does not offer pages without URLs in the page search', function (): void {
    lynx()->menus()->create('top', 'Top');

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->call('newItem', topMenuId())
        ->set('item.kind', 'page')
        ->set('pageSearch', 'menu')
        ->assertDontSee('choosePage('.(int) lynx()->menus()->root()?->id.')');
});

it('shows item validation errors', function (): void {
    lynx()->menus()->create('top', 'Top');

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->call('newItem', topMenuId())
        ->set('item.kind', 'link')
        ->set('item.label.default', 'Bad')
        ->set('item.url.default', 'javascript:alert(1)')
        ->call('saveItem')
        ->assertHasErrors('url')
        ->assertSet('isEditingItem', true);
});

it('edits an existing link with its translations and custom target', function (): void {
    lynx()->menus()->create('top', 'Top');
    $link = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => ['default' => 'Docs', 'pt' => 'Documentos'], 'url' => '/docs', 'target' => 'preview']);

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->dispatch('menu-item-edit', id: $link->id)
        ->assertSet('item.kind', 'link')
        ->assertSet('item.label', fn (array $label): bool => $label['default'] === 'Docs' && $label['pt'] === 'Documentos')
        ->assertSet('item.targetChoice', 'custom')
        ->assertSet('item.target', 'preview')
        ->set('item.label.default', 'Documentation')
        ->set('item.targetChoice', '_blank')
        ->call('saveItem')
        ->assertHasNoErrors();

    $item = lynx()->menus()->get('top')->items[0];
    expect([$item->label, $item->target])->toBe(['Documentation', '_blank']);
});

it('updates the menu settings', function (): void {
    lynx()->menus()->create('top', 'Top');

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->set('settings.title.default', 'Main menu')
        ->set('settings.class', 'site-nav')
        ->call('saveSettings')
        ->assertHasNoErrors()
        ->assertDontSee('Child page levels');

    $menu = lynx()->menus()->get('top');
    expect([$menu->title, $menu->class])->toBe(['Main menu', 'site-nav']);
});

it('deletes a menu after confirmation', function (): void {
    lynx()->menus()->create('top', 'Top');

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->call('deleteMenu')
        ->assertSet('confirmingDelete', true)
        ->call('deleteMenu')
        ->assertSet('menuId', null);

    expect(lynx()->menus()->page('top'))->toBeNull();
});

it('shows menu items in the tree with their kind and target', function (): void {
    lynx()->menus()->create('top', 'Top');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses']);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Docs', 'url' => 'https://example.com', 'target' => '_blank']);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'Novidades', 'languages' => ['pt']]);

    Livewire::test(PageTree::class, ['root' => (string) topMenuId(), 'presenter' => MenuItemPresenter::class, 'events' => 'menu-item'])
        ->assertSee(['Courses', 'Page · /courses', 'Docs', 'Link · https://example.com (_blank)', 'Section · only Português']);
});

it('refuses users the admin gate denies', function (): void {
    Gate::define('accessLynxAdmin', fn (): bool => false);

    Livewire::test(MenuEditor::class)->assertForbidden();
});

it('only deletes menus, whatever menuId the browser sends', function (): void {
    lynx()->menus()->create('top', 'Top');
    $alpha = (int) lynx()->pages->get('/courses/alpha')->id;

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->set('menuId', $alpha)
        ->call('deleteMenu')
        ->call('deleteMenu');

    expect(lynx()->pages->get($alpha))->not->toBeNull()
        ->and(lynx()->menus()->page('top'))->not->toBeNull();
});

it('does not edit items of another menu', function (): void {
    lynx()->menus()->create('top', 'Top');
    lynx()->menus()->create('footer', 'Footer');
    $topItem = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'Original']);

    Livewire::test(MenuEditor::class, ['menuId' => (int) lynx()->menus()->page('footer')?->id])
        ->call('newItem', (int) lynx()->menus()->page('footer')?->id)
        ->set('item.id', $topItem->id)
        ->set('item.label.default', 'Hijacked')
        ->call('saveItem')
        ->assertHasErrors('item');

    expect(lynx()->menus()->get('top')->items[0]->label)->toBe('Original');
});

it('saves the languages and icons of an item', function (): void {
    lynx()->menus()->create('top', 'Top');

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->call('newItem', topMenuId())
        ->assertSet('item.languages', ['default', 'pt'])
        ->set('item.kind', 'section')
        ->set('item.label.default', 'Novidades')
        ->set('item.languages', ['pt'])
        ->set('item.iconBefore', 'fa fa-star')
        ->call('saveItem')
        ->assertHasNoErrors();

    $item = lynx()->language('pt', fn () => lynx()->menus()->get('top'))->items[0];
    expect([$item->label, $item->iconBefore])->toBe(['Novidades', 'fa fa-star'])
        ->and(lynx()->menus()->get('top')->items)->toBe([]);
});

it('fills languages and icons when editing', function (): void {
    lynx()->menus()->create('top', 'Top');
    $item = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'X', 'languages' => ['pt'], 'icon_after' => 'caret']);

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->dispatch('menu-item-edit', id: $item->id)
        ->assertSet('item.languages', ['pt'])
        ->assertSet('item.iconAfter', 'caret');
});

it('sets child page levels and limit on a page item', function (): void {
    lynx()->menus()->create('top', 'Top');

    $editor = Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->call('newItem', topMenuId())
        ->set('item.kind', 'page')
        ->call('choosePage', (int) lynx()->pages->get('/courses')->id)
        ->set('item.children', true)
        ->assertSee(['Levels', 'Max pages per level'])
        ->set('item.depth', 2)
        ->set('item.limit', 1)
        ->call('saveItem')
        ->assertHasNoErrors();

    $item = lynx()->menus()->get('top')->items[0];
    expect(labels($item->children))->toBe(['Alpha']);

    $editor->dispatch('menu-item-edit', id: $item->id)
        ->assertSet('item.depth', 2)
        ->assertSet('item.limit', 1);
});

it('shows depth and limit errors next to the fields', function (): void {
    lynx()->menus()->create('top', 'Top');

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->call('newItem', topMenuId())
        ->set('item.kind', 'page')
        ->call('choosePage', (int) lynx()->pages->get('/courses')->id)
        ->set('item.children', true)
        ->set('item.limit', 500)
        ->call('saveItem')
        ->assertHasErrors('limit');
});

it('shows why a locked item cannot be saved', function (): void {
    lynx()->menus()->create('top', 'Top');
    $item = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'Fixed']);
    lynx()->pages->setStatus($item, add: ['locked']);

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->dispatch('menu-item-edit', id: $item->id)
        ->set('item.label.default', 'Changed')
        ->call('saveItem')
        ->assertHasErrors('page')
        ->assertSee('is locked');
});

it('rebuilds the selected menu from the editor', function (): void {
    lynx()->menus()->create('top', 'Top');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'A']);
    lynx()->menus()->get('top');
    expect(DB::table(MenuBuilds::BUILDS)->count())->toBe(1);

    Livewire::test(MenuEditor::class, ['menuId' => topMenuId()])
        ->assertSee('Rebuild')
        ->call('rebuild')
        ->assertDispatched('lynx-notify', message: 'Menu rebuilt: the next visit builds it again.');

    expect(DB::table(MenuBuilds::BUILDS)->count())->toBe(0);
});
