<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageCreator;
use LynxCms\Admin\Livewire\PageEditor;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('format', 'text');
    lynx()->fields->add('venue', 'text');
    lynx()->templates->add('event', fields: [
        ['name' => 'title', 'required' => true],
        'format',
        ['name' => 'venue', 'required' => true, 'show_if' => 'format=onsite|hybrid'],
        ['name' => 'summary', 'required_if' => 'format=online'],
    ]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

function eventEditor(): Testable
{
    $page = lynx()->pages->new('event', '/');
    $page->name = 'kickoff';
    $page->title = 'Kickoff';
    $page->save();

    return Livewire::test(PageEditor::class, ['pageId' => $page->id]);
}

it('shows a field only while its show_if matches', function (): void {
    eventEditor()
        ->assertDontSeeHtml('id="field-venue"')
        ->set('values.format.default', 'onsite')
        ->assertSeeHtml('id="field-venue"');
});

it('marks a field required while its required_if matches', function (): void {
    eventEditor()
        ->assertViewHas('states', fn (array $states): bool => $states['summary'] === ['shown' => true, 'required' => false])
        ->set('values.format.default', 'online')
        ->assertViewHas('states', fn (array $states): bool => $states['summary'] === ['shown' => true, 'required' => true])
        ->assertSeeHtml('aria-label="required"');
});

it('updates the form as you type in fields that conditions read', function (): void {
    eventEditor()
        ->assertSeeHtml('wire:model.live.debounce.400ms="values.format.default"')
        ->assertSeeHtml('wire:model="values.title.default"');
});

it('reports a field that became required when saving', function (): void {
    eventEditor()->set('values.format.default', 'online')->call('save')->assertHasErrors('values.summary');
});

it('asks a new page for the fields its conditions make required', function (): void {
    $creator = Livewire::test(PageCreator::class, ['parentId' => lynx()->pages->get('/')->id])->set('template', 'event');

    $creator->assertSeeHtml('id="field-format"')->assertDontSeeHtml('id="field-venue"')->assertDontSeeHtml('id="field-summary"');
    $creator->set('values.format.default', 'onsite')->assertSeeHtml('id="field-venue"');
    $creator->set('values.format.default', 'online')->assertSeeHtml('id="field-summary"')->assertDontSeeHtml('id="field-venue"');
});
