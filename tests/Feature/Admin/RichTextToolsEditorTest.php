<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Editor\RichTextTools;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

/** @param  list<string>  $tools */
function bodyWithTools(array $tools): Page
{
    lynx()->fields->update('body', ['settings' => [...lynx()->fields->get('body')->config(), 'tools' => $tools]]);

    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'tools';
    $page->title = 'Tools';
    $page->body = '<p>Body</p>';
    $page->save();

    return $page;
}

it('offers the extra tools on a rich text field', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'body'])
        ->assertSeeHtml('data-setting-tools="superscript"')
        ->assertSeeHtml('data-setting-tools="text_align"')
        ->set('settings.tools', ['superscript', 'text_align'])
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('body')->config()['tools'] ?? null)->toBe(['superscript', 'text_align']);
});

it('turns every extra tool off again', function (): void {
    lynx()->fields->update('body', ['settings' => [...lynx()->fields->get('body')->config(), 'tools' => ['code']]]);

    Livewire::test(FieldForm::class, ['field' => 'body'])
        ->assertSet('settings.tools', ['code'])
        ->set('settings.tools', [])
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('body')->config())->not->toHaveKey('tools');
});

it('shows no extra tool in the editor until the field turns it on', function (): void {
    $page = bodyWithTools([]);

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertDontSeeHtml('data-richtext-tool="superscript"')
        ->assertSeeHtml('data-richtext-tool="bold"');
});

it('shows the tools the field turns on, in the editor', function (): void {
    $page = bodyWithTools(['superscript', 'text_align']);

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('data-richtext-tool="superscript"')
        ->assertSeeHtml('data-richtext-tool="alignCenter"')
        ->assertDontSeeHtml('data-richtext-tool="subscript"');
});

it('lists the buttons of the tools in a fixed order, whatever order the field keeps', function (): void {
    $buttons = array_column(app(RichTextTools::class)->buttons(['text_align', 'underline', 'unknown']), 'tool');

    expect($buttons)->toBe(['underline', 'alignLeft', 'alignCenter', 'alignRight', 'alignJustify']);
});
