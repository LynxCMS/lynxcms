<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Menus\Admin\MenuEditor;
use LynxCms\Menus\MenuSchema;

beforeEach(function (): void {
    courseSite();
    lynx()->menus()->create('top', 'Top');
});

/** The rel of the menu's first item. */
function firstRel(): ?string
{
    return lynx()->menus()->get('top')->items[0]->rel;
}

function saveLink(array $input): void
{
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Partner', 'url' => 'https://example.com', ...$input]);
}

it('gives new windows noopener only', function (): void {
    saveLink(['target' => '_blank']);

    expect(firstRel())->toBe('noopener')
        ->and(Blade::render('<x-lynx-menu name="top" />'))->toContain('rel="noopener"')->not->toContain('noreferrer');
});

it('keeps the relations chosen, adding noopener for new windows', function (?string $target, array $rel, ?string $expected): void {
    saveLink(['target' => $target, 'rel' => $rel]);

    expect(firstRel())->toBe($expected);
})->with([
    'new window' => ['_blank', ['sponsored', 'noreferrer'], 'sponsored noreferrer noopener'],
    'same window' => [null, ['nofollow'], 'nofollow'],
    'nothing chosen' => [null, [], null],
]);

it('refuses relations editors may not choose', function (): void {
    expect(fn () => saveLink(['rel' => ['author']]))->toThrow(ValidationException::class);
});

it('adds the relation field to menus installed before it', function (): void {
    lynx()->templates->update(MenuSchema::ITEM_TEMPLATE, ['remove' => [MenuSchema::REL]]);

    expect(firstRelAfterSaving())->toBe('nofollow');
});

function firstRelAfterSaving(): ?string
{
    saveLink(['rel' => ['nofollow']]);

    return firstRel();
}

it('sets relations in the item form', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    saveLink(['target' => '_blank']);
    $id = lynx()->menus()->page('top')?->children()->first()?->id;

    Livewire::test(MenuEditor::class, ['menuId' => (int) lynx()->menus()->page('top')?->id])
        ->dispatch('menu-item-edit', id: $id)
        ->assertSeeHtml('id="item-rel-noreferrer"')
        ->set('item.rel', ['noreferrer'])
        ->call('saveItem')
        ->assertHasNoErrors();

    expect(firstRel())->toBe('noreferrer noopener');
});
