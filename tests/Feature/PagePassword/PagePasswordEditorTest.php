<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\PagePasswordSchema;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    ['alpha' => $this->alpha] = createCourses('alpha');
});

it('shows editors the password and saves a new one encrypted', function (): void {
    $this->alpha->{PagePasswordSchema::FIELD} = 'first';
    $this->alpha->save();

    Livewire::test(PageEditor::class, ['pageId' => $this->alpha->id])
        ->assertSet('values.'.PagePasswordSchema::FIELD.'.default', 'first')
        ->assertSeeHtml('autocomplete="new-password"')
        ->set('values.'.PagePasswordSchema::FIELD.'.default', 'second')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($this->alpha->id)?->get(PagePasswordSchema::FIELD)->matches('second'))->toBeTrue();
});

it('shows only whether a password is set when the field is locked', function (): void {
    $this->alpha->{PagePasswordSchema::FIELD} = 'secret-word';
    $this->alpha->save();
    lynx()->templates->update('course', ['add' => [['name' => PagePasswordSchema::FIELD, 'editor' => 'locked']]]);

    Livewire::test(PageEditor::class, ['pageId' => $this->alpha->id])
        ->assertSee('Set')
        ->assertDontSee('secret-word')
        ->assertDontSee((string) lynx()->pages->get($this->alpha->id)?->getUnformatted(PagePasswordSchema::FIELD));
});
