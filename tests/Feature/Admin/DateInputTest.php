<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('starts_on', 'date');
    lynx()->fields->add('starts_at', 'date', settings: ['time' => true]);
    lynx()->templates->update('course', ['add' => ['starts_on', 'starts_at']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->starts_on = '2026-03-09';
    $alpha->starts_at = '2026-03-09 14:30';
    $alpha->save();
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('edits dates with native date and date-time inputs', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->assertSeeHtml('type="date"')
        ->assertSeeHtml('type="datetime-local"')
        ->assertSet('values.starts_on.default', '2026-03-09')
        ->assertSet('values.starts_at.default', '2026-03-09T14:30')
        ->set('values.starts_on.default', '2026-04-01')
        ->set('values.starts_at.default', '2026-04-01T09:15')
        ->call('save')
        ->assertHasNoErrors();

    $page = lynx()->pages->get($this->alphaId);

    expect($page?->starts_on?->toDateString())->toBe('2026-04-01')
        ->and($page?->starts_at?->format('Y-m-d H:i'))->toBe('2026-04-01 09:15');
});

it('clears a date', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->set('values.starts_on.default', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($this->alphaId)?->starts_on)->toBeNull();
});
