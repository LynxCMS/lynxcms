<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\TemplateForm;
use LynxCms\Core\Templates\Layout\LayoutBlock;
use LynxCms\Core\Templates\Layout\LayoutTab;
use LynxCms\Core\Templates\Layout\TemplateLayout;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->templates->update('course', [
        'tabs' => [['key' => 'extra', 'label' => 'Extra']],
        'fields' => [
            'title',
            ['section' => 'intro', 'label' => 'Introduction', 'fields' => ['summary', 'body']],
            'price',
            ['name' => 'related', 'tab' => 'extra'],
        ],
    ]);
});

function builder(): Testable
{
    return Livewire::test(TemplateForm::class, ['template' => 'course']);
}

function savedLayout(): TemplateLayout
{
    return TemplateLayout::for(lynx()->templates->get('course'));
}

/** @return list<string> */
function builderBlocks(TemplateLayout $layout, string $tab, string $column = 'main'): array
{
    return array_map(fn (LayoutBlock $b): ?string => $b->isSection() ? 'section:'.$b->section->key.'('.implode(',', $b->fields).')' : $b->field, $layout->blocks($tab, $column));
}

it('loads the layout as a board of tabs, columns and sections', function (): void {
    builder()
        ->assertSet('board.content.main', ['field:title', 'section:intro', 'field:price'])
        ->assertSet('board.extra.main', ['field:related'])
        ->assertSet('sectionFields.intro', ['summary', 'body'])
        ->assertSee('Introduction')
        ->assertSeeHtml('wire:sort:group-id="section:intro"')
        ->assertSeeHtml('wire:sort:group-id="tab:extra:sidebar"');
});

it('moves fields into sections, to the sidebar and to other tabs, and saves', function (): void {
    builder()
        ->call('moveItem', 'field:price', 0, 'section:intro')
        ->call('moveItem', 'field:title', 0, 'tab:content:sidebar')
        ->call('placeItem', 'section:intro', 'tab:extra:main')
        ->assertSet('order', ['title', 'related', 'price', 'summary', 'body'])
        ->call('saveFields')
        ->assertHasNoErrors();

    $layout = savedLayout();

    expect(builderBlocks($layout, 'content', 'sidebar'))->toBe(['title'])
        ->and(builderBlocks($layout, 'extra'))->toBe(['related', 'section:intro(price,summary,body)'])
        ->and(builderBlocks($layout, 'content'))->toBe([]);
});

it('does not put a section inside another section', function (): void {
    builder()
        ->set('newSection.default', 'Media')
        ->call('addSection')
        ->call('moveItem', 'section:media', 0, 'section:intro')
        ->assertSet('board.content.main', ['field:title', 'section:intro', 'field:price', 'section:media']);
});

it('refuses a tab or section without a name', function (): void {
    builder()->call('addSection')->assertHasErrors('layout')->call('addTab')->assertHasErrors('layout');
});

it('adds and removes tabs and sections without losing fields', function (): void {
    builder()
        ->set('newTab.default', 'Search engines')
        ->call('addTab')
        ->assertSet('tabs.1.key', 'search_engines')
        ->call('placeItem', 'field:price', 'tab:search_engines:main')
        ->call('removeSection', 'intro')
        ->assertSet('board.content.main', ['field:title', 'field:summary', 'field:body'])
        ->call('removeTab', 'extra')
        ->assertSet('board.content.main', ['field:title', 'field:summary', 'field:body', 'field:related'])
        ->call('saveFields')
        ->assertHasNoErrors();

    $layout = savedLayout();

    expect(array_map(fn (LayoutTab $t): string => $t->key, $layout->tabs()))->toBe(['content', 'search_engines'])
        ->and(builderBlocks($layout, 'search_engines'))->toBe(['price'])
        ->and($layout->section('intro'))->toBeNull();
});

it('saves a section that starts collapsed with its label in every language', function (): void {
    builder()
        ->set('sections.intro.collapsed', true)
        ->set('sections.intro.label.pt', 'Introdução')
        ->call('saveFields')
        ->assertHasNoErrors();

    expect(savedLayout()->section('intro')?->collapsed)->toBeTrue()
        ->and(savedLayout()->section('intro')?->label->in('pt'))->toBe('Introdução');
});
