<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('opens', 'time');
    lynx()->fields->add('lap', 'time', settings: ['seconds' => true]);
    lynx()->templates->update('course', ['add' => ['opens', 'lap']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->opens = '09:30';
    $alpha->save();
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('edits times with a native time input, with seconds where the field keeps them', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->assertSeeHtml('type="time"')
        ->assertSeeHtml('step="1"')
        ->assertSet('values.opens.default', '09:30')
        ->set('values.opens.default', '10:15')
        ->set('values.lap.default', '00:01:30')
        ->call('save')
        ->assertHasNoErrors();

    $page = lynx()->pages->get($this->alphaId);

    expect($page?->opens)->toBe('10:15')
        ->and($page?->lap)->toBe('00:01:30');
});

it('shows the error for a time that does not exist', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->set('values.opens.default', '25:00')
        ->call('save')
        ->assertHasErrors('values.opens');
});
