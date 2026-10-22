<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

/*
 * ADR-0063 in the page editor: locked fields show their value read only, hidden fields are left
 * out, and neither is ever sent to pages.save; collapsed modes, width, placeholder and icon.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'kept';
    $page->title = 'Kept';
    $page->price = 120;
    $page->save();
    $this->page = $page;
});

function courseEditorSettings(array $fields): void
{
    lynx()->templates->update('course', ['add' => $fields]);
}

it('shows locked fields read only and leaves hidden fields out', function (): void {
    courseEditorSettings([['name' => 'price', 'editor' => 'locked'], ['name' => 'body', 'editor' => 'hidden']]);

    Livewire::test(PageEditor::class, ['pageId' => $this->page->id])
        ->assertSeeHtml('data-field="price" data-editor="locked"')
        ->assertDontSeeHtml('wire:model="values.price')
        ->assertSee('120')
        ->assertDontSeeHtml('data-field="body"');
});

it('never saves locked or hidden fields from the editor', function (): void {
    courseEditorSettings([['name' => 'price', 'editor' => 'locked'], ['name' => 'summary', 'editor' => 'hidden']]);

    Livewire::test(PageEditor::class, ['pageId' => $this->page->id])
        ->set('values.price.default', '999')
        ->set('values.summary.default', 'Smuggled')
        ->set('values.title.default', 'Renamed')
        ->call('save');

    $page = lynx()->pages->get('/courses/kept/');
    expect($page?->price)->toBe(120)
        ->and($page?->getUnformatted('summary'))->toBeNull()
        ->and($page?->getUnformatted('title'))->toBe('Renamed');
});

it('lets conditions read a locked field', function (): void {
    courseEditorSettings([['name' => 'price', 'editor' => 'locked'], ['name' => 'summary', 'show_if' => 'price>100']]);

    Livewire::test(PageEditor::class, ['pageId' => $this->page->id])->assertSeeHtml('data-field="summary"');
});

it('starts collapsed fields closed or open by their value', function (): void {
    courseEditorSettings([['name' => 'summary', 'editor' => 'collapsed_blank'], ['name' => 'price', 'editor' => 'collapsed_populated'], ['name' => 'body', 'editor' => 'collapsed']]);

    Livewire::test(PageEditor::class, ['pageId' => $this->page->id])
        ->assertSeeHtml('data-field="summary" data-editor="collapsed_blank" data-collapsed="true"')
        ->assertSeeHtml('data-field="price" data-editor="collapsed_populated" data-collapsed="true"')
        ->assertSeeHtml('data-field="body" data-editor="collapsed" data-collapsed="true"')
        ->assertSeeHtml('data-field="title" data-editor="open" data-collapsed="false"');
});

it('places fields by width, with their placeholder and icon', function (): void {
    courseEditorSettings([['name' => 'summary', 'width' => 50, 'placeholder' => 'One line about the course', 'icon' => 'text']]);

    Livewire::test(PageEditor::class, ['pageId' => $this->page->id])
        ->assertSeeHtml('data-width="50"')
        ->assertSeeHtml('placeholder="One line about the course"')
        ->assertSeeHtml('data-icon="text"');
});
