<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\PageTableInput;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('starts', 'time');
    lynx()->templates->add('session', fields: [['name' => 'title', 'required' => true], 'starts']);
    $this->settings = ['templates' => ['session'], 'columns' => ['title', 'starts']];
    lynx()->fields->add('sessions', 'page_table', settings: $this->settings);
    lynx()->templates->update('course', ['add' => ['sessions']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('shows the table with its columns in the page editor', function (): void {
    $session = lynx()->run(SavePage::class, ['template' => 'session', 'parent' => $this->alphaId, 'values' => ['title' => ['default' => 'Morning'], 'starts' => '09:00']])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $this->alphaId, 'values' => ['sessions' => [$session->id]]]);

    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])->assertSeeLivewire(PageTableInput::class);
    Livewire::test(PageTableInput::class, ['value' => [$session->id], 'pageId' => $this->alphaId, 'settings' => $this->settings])
        ->assertSee('Starts')->assertSee('Morning')->assertSee('09:00');
});

it('makes a new hidden page under the page and adds it to the list', function (): void {
    $component = Livewire::test(PageTableInput::class, ['value' => [], 'pageId' => $this->alphaId, 'settings' => $this->settings])
        ->set('title', 'Evening')->call('create')->assertHasNoErrors();

    $created = lynx()->pages->get($component->get('value')[0]);

    expect($created?->template()->name)->toBe('session')
        ->and($created?->parentId)->toBe($this->alphaId)
        ->and($created?->hasStatus(PageStatus::HIDDEN))->toBeTrue()
        ->and((string) $created?->title)->toBe('Evening');

    $component->set('title', '')->call('create')->assertHasErrors('title');
});

it('moves rows and removes them, trashing the page only when the field says so', function (): void {
    $make = fn (string $title): int => (int) lynx()->run(SavePage::class, ['template' => 'session', 'parent' => $this->alphaId, 'values' => ['title' => ['default' => $title]]])->valueAs(Page::class)->id;
    [$a, $b] = [$make('A'), $make('B')];

    Livewire::test(PageTableInput::class, ['value' => [$a, $b], 'pageId' => $this->alphaId, 'settings' => $this->settings])
        ->call('move', $b, -1)->assertSet('value', [$b, $a])
        ->call('remove', $a)->assertSet('value', [$b]);
    expect(lynx()->pages->get($a))->toBeInstanceOf(Page::class);

    Livewire::test(PageTableInput::class, ['value' => [$b], 'pageId' => $this->alphaId, 'settings' => [...$this->settings, 'remove' => 'trash']])
        ->call('remove', $b)->assertSet('value', []);
    expect(lynx()->pages->findOne("id={$b}"))->toBeNull();
});

it('asks to save a new page before adding items', function (): void {
    Livewire::test(PageTableInput::class, ['value' => [], 'pageId' => null, 'settings' => $this->settings])
        ->assertSee('Save the page first')->call('create')->assertForbidden();
});
