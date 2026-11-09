<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\TemplateForm;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->templates->update('course', [
        'fields' => ['title', ['section' => 'intro', 'label' => 'Introduction', 'fields' => ['summary', 'body']], 'price', 'related'],
    ]);
    lynx()->templates->add('event', fields: ['title']);
    lynx()->templates->add('lesson', fields: ['title', 'summary']);
});

function copyDialog(): Testable
{
    return Livewire::test(TemplateForm::class, ['template' => 'course'])->call('openCopySection', 'intro');
}

it('offers every other template, with the reason a template cannot take the section', function (): void {
    $targets = copyDialog()->get('copyTargets');

    expect($targets)->toHaveKeys(['courses', 'event', 'lesson'])
        ->not->toHaveKey('course')
        ->and($targets['event']['problem'])->toBeNull()
        ->and($targets['lesson']['problem'])->not->toBeNull()
        ->and($targets['lesson']['renames'])->toBe(['summary' => 'intro_summary']);
});

it('lets a clashing template take the section once its fields are renamed', function (): void {
    $targets = copyDialog()->set('renameConflicts', true)->set('copyPrefix', 'lesson_')->get('copyTargets');

    expect($targets['lesson']['problem'])->toBeNull()
        ->and($targets['lesson']['renames'])->toBe(['summary' => 'lesson_summary']);
});

it('copies the section to the ticked templates', function (): void {
    copyDialog()
        ->set('copyTo', ['event', 'lesson'])
        ->set('renameConflicts', true)
        ->call('copySectionTo')
        ->assertHasNoErrors()
        ->assertSet('copySection', '');

    expect(lynx()->templates->fields('event')->keys()->all())->toBe(['title', 'summary', 'body'])
        ->and(lynx()->templates->fields('lesson')->keys()->all())->toBe(['title', 'summary', 'intro_summary', 'body']);
});

it('shows the action\'s error when the copy is refused', function (): void {
    copyDialog()
        ->set('copyTo', ['lesson'])
        ->call('copySectionTo')
        ->assertHasErrors('to');

    expect(lynx()->templates->fields('lesson')->keys()->all())->toBe(['title', 'summary']);
});

it('asks to save a section the template does not have yet', function (): void {
    $targets = Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->set('newSection.default', 'Extra')
        ->call('addSection')
        ->call('openCopySection', 'extra')
        ->assertSet('isCopySectionSaved', false)
        ->get('copyTargets');

    expect($targets)->toBe([]);
});

it('tells editors under Add a section that sections can be copied', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])->assertSeeHtml('data-copy-section-hint');
});

it('works out which templates can take the section when it opens and when the renaming changes', function (): void {
    $dialog = copyDialog();
    lynx()->templates->update('event', ['add' => ['summary']]);

    expect($dialog->set('copyTo', ['event'])->get('copyTargets')['event']['problem'])->toBeNull()
        ->and($dialog->set('copyPrefix', 'event_')->get('copyTargets')['event']['problem'])->not->toBeNull();
});
