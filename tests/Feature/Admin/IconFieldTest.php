<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Icons\IconSet;
use LynxCms\Admin\Livewire\IconPicker;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\TemplateForm;
use LynxCms\Core\Pages\Page;

/*
 * The icon field type: an icon name from the admin's Lucide set, chosen with a picker that
 * previews each icon. The worked example in docs/reference/field-types.md.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->fields->add('badge', 'icon');
    lynx()->templates->update('course', ['add' => ['badge']]);
});

function courseWithBadge(?string $badge): Page
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'with-badge';
    $page->title = 'With badge';
    $page->badge = $badge;
    $page->save();

    return $page;
}

it('lists and searches the icons the admin has', function (): void {
    $icons = app(IconSet::class);

    expect($icons->has('calendar'))->toBeTrue()
        ->and($icons->has('not-an-icon'))->toBeFalse()
        ->and($icons->search('calend', 10))->toContain('calendar')
        ->and(count($icons->search('', 60)))->toBe(60);
});

it('stores an icon name and refuses unknown ones', function (): void {
    expect(courseWithBadge(' calendar ')->badge)->toBe('calendar');

    courseWithBadge('not-an-icon');
})->throws(ValidationException::class);

it('picks an icon by searching, with previews', function (): void {
    Livewire::test(IconPicker::class, ['value' => ''])
        ->set('search', 'calend')
        ->assertSeeHtml('data-icon-option="calendar"')
        ->assertSeeHtml('<svg')
        ->call('choose', 'calendar')
        ->assertSet('value', 'calendar')
        ->assertSet('search', '')
        ->call('clear')
        ->assertSet('value', '');
});

it('refuses to pick an icon that does not exist', function (): void {
    Livewire::test(IconPicker::class, ['value' => 'calendar'])->call('choose', 'not-an-icon')->assertSet('value', 'calendar');
});

it('edits icon fields in the page editor with the picker', function (): void {
    $page = courseWithBadge('calendar');

    Livewire::test(PageEditor::class, ['pageId' => $page->id])->assertSeeLivewire(IconPicker::class);
});

it('picks the icon of a template field with the picker', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])->assertSeeLivewire(IconPicker::class);
});
