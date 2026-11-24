<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;
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
        ->assertSeeHtml('data-field="price" data-editor="open"')
        ->assertSeeHtml('data-locked="true"')
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

it('shows a field locked on its own as read only on every template (ADR-0149)', function (): void {
    lynx()->fields->update('price', ['settings' => ['locked' => true]]);

    Livewire::test(PageEditor::class, ['pageId' => $this->page->id])
        ->assertSeeHtml('data-field="price" data-editor="open" data-collapsed="false" data-width="100" data-locked="true"')
        ->assertSeeHtml('data-field="summary" data-editor="open" data-collapsed="false" data-width="100" data-locked="false"');
});

it('renders a lazy closed field once it is opened', function (): void {
    courseEditorSettings([['name' => 'summary', 'editor' => 'collapsed', 'lazy' => true]]);

    Livewire::test(PageEditor::class, ['pageId' => $this->page->id])
        ->assertSeeHtml('data-locked="false" data-deferred="true"')
        ->assertDontSeeHtml('wire:model="values.summary.default"')
        ->call('openField', 'summary')
        ->assertSet('openedFields', ['summary'])
        ->assertDontSeeHtml('data-deferred="true"');
});

it('renders a lazy field that starts open at once', function (): void {
    courseEditorSettings([['name' => 'price', 'editor' => 'collapsed_blank', 'lazy' => true]]);

    Livewire::test(PageEditor::class, ['pageId' => $this->page->id])
        ->assertDontSeeHtml('data-deferred="true"');
});

it('lets a field open and close that starts open', function (): void {
    courseEditorSettings([['name' => 'summary', 'editor' => 'open_collapsible']]);

    Livewire::test(PageEditor::class, ['pageId' => $this->page->id])
        ->assertSeeHtml('data-field="summary" data-editor="open_collapsible" data-collapsed="false"')
        ->assertSeeHtml('aria-controls="field-summary-input"');
});

it('locks a field on the field form', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'price'])
        ->assertSeeHtml('data-field-locked')
        ->set('locked', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('price')->isLocked())->toBeTrue();
});
