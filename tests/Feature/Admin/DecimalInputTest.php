<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('rating', 'decimal', settings: ['places' => 1]);
    lynx()->templates->update('course', ['add' => ['rating']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->rating = 4.5;
    $alpha->save();
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('edits a decimal with the decimal keypad, accepting a comma', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->assertSeeHtml('inputmode="decimal"')
        ->assertSet('values.rating.default', '4.5')
        ->set('values.rating.default', '3,25')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($this->alphaId)?->rating)->toBe(3.3);
});

it('shows the error for text that is not a number', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->set('values.rating.default', 'many')
        ->call('save')
        ->assertHasErrors();
});
