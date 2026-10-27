<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

/*
 * The sentences that explain fallbacks ("visitors see the English value") are translation hints:
 * hidden unless an editor turns them on in their user menu, a choice each browser remembers.
 * The language tabs' state markers say the same thing without words.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('marks the fallback sentence as a translation hint', function (): void {
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'hinted';
    $page->title = 'Hinted';
    $page->summary = 'Only in the default language.';
    lynx()->pages->save($page);

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('class="lynx-hint');
});

it('offers translation hints in the user menu, and restores the choice before the page paints', function (): void {
    $html = (string) $this->get('/lynx/pages')->getContent();

    expect($html)->toContain('data-lynx-hints-toggle')
        ->and($html)->toContain("localStorage.getItem('lynx:translation-hints')");
});

it('leaves translation hints out of a site with one language', function (): void {
    lynx()->languages->delete('pt');

    expect((string) $this->get('/lynx/pages')->getContent())->not->toContain('data-lynx-hints-toggle');
});
