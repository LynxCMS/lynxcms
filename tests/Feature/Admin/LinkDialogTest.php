<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Editor\LinkFiles;
use LynxCms\Admin\Livewire\LinkDialog;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    $alpha = createCourses('alpha', 'beta')['alpha'];
    $alpha->setLanguageName('pt', 'alfa');
    $alpha->setLanguageValue('pt', 'title', 'Alfa');
    $alpha->save();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function linkDialog(string $language = 'default', array $link = []): Testable
{
    return Livewire::test(LinkDialog::class)
        ->call('open', ...['requestId' => 'r1', 'pageId' => (int) lynx()->pages->get('/courses/beta')->id, 'language' => $language, ...$link]);
}

function idOf(string $path): int
{
    return (int) lynx()->pages->get($path)->id;
}

it('opens with the link being edited, keeping only the relations editors choose', function (): void {
    linkDialog(link: ['href' => '/x', 'text' => 'Read', 'title' => 'More', 'target' => '_blank', 'rel' => 'noopener noreferrer nofollow'])
        ->assertSet('href', '/x')
        ->assertSet('text', 'Read')
        ->assertSet('title', 'More')
        ->assertSet('target', '_blank')
        ->assertSet('rel', ['nofollow', 'noreferrer'])
        ->assertDispatched('open-dialog-lynx-link');
});

it('finds pages when words are typed instead of an address', function (): void {
    linkDialog()
        ->set('href', 'alp')
        ->assertSeeHtml('wire:click="choosePage('.idOf('/courses/alpha').')"')
        ->assertDontSeeHtml('wire:click="choosePage('.idOf('/courses/beta').')"')
        ->set('href', 'https://alp')
        ->assertDontSeeHtml('wire:click="choosePage(');
});

it('links a page by its address in the language being edited, with its title as text', function (): void {
    linkDialog('pt')
        ->call('choosePage', idOf('/courses/alpha'))
        ->assertSet('href', '/pt/cursos/alfa')
        ->assertSet('text', 'Alfa');
});

it('keeps text the editor selected when choosing a page', function (): void {
    linkDialog(link: ['text' => 'this course'])
        ->call('choosePage', idOf('/courses/alpha'))
        ->assertSet('href', '/courses/alpha')
        ->assertSet('text', 'this course');
});

it('browses the page tree a level at a time', function (): void {
    linkDialog()
        ->call('browse', idOf('/courses'))
        ->assertSee(['Alpha', 'Beta'])
        ->assertSeeHtml('wire:click="browse('.idOf('/').')"');
});

it('searches pages by title in Select page, in place of the tree', function (): void {
    linkDialog()
        ->call('browse', idOf('/courses'))
        ->set('pageQuery', 'alp')
        ->assertSeeHtml('wire:click="choosePage('.idOf('/courses/alpha').')"')
        ->assertDontSeeHtml('wire:click="choosePage('.idOf('/courses/beta').')"')
        ->assertSee('Pages matching')
        ->call('choosePage', idOf('/courses/alpha'))
        ->assertSet('href', '/courses/alpha')
        ->assertSet('pageQuery', '');
});

it('finds pages by the text of their searchable fields', function (): void {
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true]]]);
    $alpha = lynx()->pages->get('/courses/alpha');
    $alpha->summary = 'Filmado ao longo das quatro estações';
    $alpha->save();

    linkDialog()
        ->set('href', 'quatro estacoes')
        ->assertSeeHtml('wire:click="choosePage('.idOf('/courses/alpha').')"')
        ->call('show', 'pages')
        ->set('pageQuery', 'estações')
        ->assertSeeHtml('wire:click="choosePage('.idOf('/courses/alpha').')"');
});

it('lists the files modules offer for the page', function (): void {
    app(LinkFiles::class)->add(fn (Page $page): array => [['name' => 'brochure-'.$page->name().'.pdf', 'url' => '/media/brochure.pdf']]);

    linkDialog()
        ->call('show', 'files')
        ->assertSee('brochure-beta.pdf')
        ->call('chooseFile', 0)
        ->assertSet('href', '/media/brochure.pdf')
        ->assertSet('text', 'brochure-beta.pdf');
});

it('sends the link back to the editor that asked', function (): void {
    linkDialog(link: ['text' => 'Partner'])
        ->set('href', 'https://example.com/')
        ->set('title', 'Our partner')
        ->set('target', '_blank')
        ->set('rel', ['sponsored', 'noreferrer'])
        ->call('insert')
        ->assertHasNoErrors()
        ->assertDispatched('lynx-link-chosen', requestId: 'r1', href: 'https://example.com/', text: 'Partner', title: 'Our partner', target: '_blank', rel: 'sponsored noreferrer')
        ->assertDispatched('close-dialog-lynx-link');
});

it('refuses addresses that could run script, and unknown attributes', function (string $property, mixed $value, string $error): void {
    linkDialog()
        ->set('href', 'https://example.com/')
        ->set($property, $value)
        ->call('insert')
        ->assertHasErrors($error)
        ->assertNotDispatched('lynx-link-chosen');
})->with([
    'javascript' => ['href', 'javascript:alert(1)', 'href'],
    'data' => ['href', 'data:text/html,x', 'href'],
    'protocol relative' => ['href', '//evil.example/', 'href'],
    'target' => ['target', '_parent', 'target'],
    'rel' => ['rel', ['author'], 'rel.0'],
]);

it('removes the link', function (): void {
    linkDialog(link: ['href' => '/x', 'text' => 'Read'])
        ->call('unlink')
        ->assertDispatched('lynx-link-chosen', requestId: 'r1', href: '');
});

it('offers Remove link only when editing a link', function (): void {
    linkDialog(link: ['text' => 'new'])->assertDontSee('Remove link');
    linkDialog(link: ['href' => '/x', 'text' => 'old'])->assertSee('Remove link');
});

it('offers to open only pages that may have children', function (): void {
    lynx()->templates->update('course', ['allow_children' => false]);

    linkDialog()
        ->call('browse', idOf('/courses'))
        ->assertDontSeeHtml('wire:click="browse('.idOf('/courses/alpha').')"');
});

it('links a section of the page being edited, or of the page chosen', function (): void {
    lynx()->fields->update('body', ['settings' => ['formatters' => ['heading_anchors']]]);
    foreach (['/courses/alpha' => '<h2>Prices</h2><h2>Dates</h2>', '/courses/beta' => '<h2>Who it is for</h2>'] as $path => $body) {
        $page = lynx()->pages->get($path);
        $page->body = $body;
        $page->save();
    }

    linkDialog()->call('show', 'anchors')->assertSee('Who it is for')
        ->call('chooseAnchor', 'who-it-is-for')->assertSet('href', '#who-it-is-for');

    linkDialog()->call('choosePage', idOf('/courses/alpha'))
        ->call('show', 'anchors')->assertSee('Prices')->assertDontSee('Who it is for')
        ->call('chooseAnchor', 'dates')->assertSet('href', '/courses/alpha#dates')->assertSet('text', 'Alpha');
});

it('offers the files of the page chosen next to those of the page being edited', function (): void {
    app(LinkFiles::class)->add(fn (Page $page): array => [['name' => 'brochure-'.$page->name().'.pdf', 'url' => '/media/'.$page->name().'.pdf']]);

    linkDialog()->call('choosePage', idOf('/courses/alpha'))
        ->call('show', 'files')->assertSee('brochure-beta.pdf')->assertSee('Files of Alpha')->assertSee('brochure-alpha.pdf')
        ->call('chooseFile', 0, true)->assertSet('href', '/media/alpha.pdf');
});
