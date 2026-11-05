<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Component;
use Livewire\Livewire;
use LynxCms\Admin\AdminSlots;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Pages\Page;

/*
 * Modules add Livewire components to named places in the admin (AdminSlots): the page editor's
 * header shows those of "editor.header" for the pages each one is for. A module that writes to
 * the draft says so with "lynx-draft-changed", and the editor reloads it, keeping unsaved edits.
 */

final class SlotFixture extends Component
{
    public int $pageId;

    public function render(): string
    {
        return '<div data-slot-fixture="{{ $pageId }}"></div>';
    }
}

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    ['alpha' => $alpha, 'beta' => $beta] = createCourses('alpha', 'beta');
    $this->alphaId = (int) $alpha->id;
    $this->betaId = (int) $beta->id;
    Livewire::component('slot-fixture', SlotFixture::class);
});

it('shows a slot\'s component in the editor header, for the pages it is for', function (): void {
    app(AdminSlots::class)->add('editor.header', 'slot-fixture', fn (Page $page): bool => (int) $page->id === $this->alphaId);

    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])->assertSeeHtml('data-slot-fixture="'.$this->alphaId.'"');
    Livewire::test(PageEditor::class, ['pageId' => $this->betaId])->assertDontSeeHtml('data-slot-fixture');
});

it('lists a slot\'s components for every page when it names no condition', function (): void {
    app(AdminSlots::class)->add('editor.header', 'slot-fixture');

    expect(app(AdminSlots::class)->for('editor.header', lynx()->pages->get($this->betaId)))->toBe(['slot-fixture'])
        ->and(app(AdminSlots::class)->for('tree.toolbar', lynx()->pages->get($this->betaId)))->toBe([]);
});

it('reloads the draft a module changed, keeping the editor\'s unsaved edits', function (): void {
    $editor = Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])->set('values.price.default', 99);
    lynx()->pages->saveDraft($this->alphaId, ['title' => ['pt' => 'Alfa']]);

    $editor->dispatch('lynx-draft-changed')
        ->assertSet('values.title.pt', 'Alfa')
        ->assertSet('values.price.default', 99)
        ->assertSeeHtml('data-page-draft');
});
