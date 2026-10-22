<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function richPage(): Page
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'rich';
    $page->title = 'Rich';
    $page->summary = 'Plain summary';
    $page->body = '<p>English <strong>body</strong></p>';
    $page->save();

    return $page;
}

it('serves the admin script', function (): void {
    $this->get('/lynx/assets/admin.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
});

it('loads the admin script before Livewire starts Alpine', function (): void {
    $html = $this->get('/lynx/pages')->getContent();

    expect(strpos($html, '/lynx/assets/admin.js?v='))->toBeInt()
        ->and(strpos($html, '/lynx/assets/admin.js?v='))->toBeLessThan((int) strpos($html, '/livewire.'));
});

it('edits html textareas with the rich text editor and plain ones with a textarea', function (): void {
    $page = richPage();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml("lynxRichText(\$wire.\$entangle('values.body.default', false)")
        ->assertSeeHtml('wire:model="values.summary.default"')
        ->assertDontSeeHtml("\$entangle('values.summary.default'");
});

it('shows the fallback text, without tags, as the placeholder of an empty translation', function (): void {
    $page = richPage();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('English body');
});

it('saves rich text through pages.save, which cleans it', function (): void {
    $page = richPage();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->set('values.body.pt', '<p>Corpo <em>português</em><script>alert(1)</script></p>')
        ->call('save')
        ->assertHasNoErrors();

    expect((string) lynx()->pages->get($page->id)->getLanguageValue('pt', 'body'))->toBe('<p>Corpo <em>português</em></p>');
});
