<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Admin\Livewire\PageEditor;

/*
 * A toggle's "input" setting: one checkbox labeled Yes (default), or a switch. The page editor and
 * the field form's Preview tab draw the one it names, and both save the same true or false.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->create('featured', 'toggle');
    lynx()->templates->update('course', ['add' => ['featured']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('edits a toggle with a checkbox by default', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->assertSeeHtml('data-toggle-input="checkbox"')
        ->assertDontSeeHtml('role="switch"');
});

it('edits a toggle with a switch when its input says so, and saves it', function (): void {
    lynx()->fields->update('featured', ['settings' => ['input' => 'switch']]);

    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->assertSeeHtml('data-toggle-input="switch"')
        ->assertSeeHtml('role="switch"')
        ->set('values.featured.default', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($this->alphaId)?->featured)->toBeTrue();
});

it('previews a toggle as a switch when its input says so', function (): void {
    lynx()->fields->update('featured', ['settings' => ['input' => 'switch']]);

    Livewire::test(FieldForm::class, ['field' => 'featured'])
        ->assertSeeHtml('data-preview-input="switch"');
});

it('refuses an input a toggle does not have', function (): void {
    lynx()->fields->update('featured', ['settings' => ['input' => 'slider']]);
})->throws(ValidationException::class);
