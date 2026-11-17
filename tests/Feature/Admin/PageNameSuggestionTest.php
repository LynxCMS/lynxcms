<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Core\Pages\Page;

/*
 * Clearing a URL name in the Settings tab and leaving the field fills it again: the default
 * language's from the page's title, another language's from its own title when the page has one,
 * else from the default name. A refilled default name also replaces the copies of the old one in
 * languages without a title of their own; names typed by hand stay. Nothing is saved until the
 * editor saves the names.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function namedPage(?string $portugueseTitle, string $portugueseName = 'nome-antigo'): Page
{
    $page = lynx()->pages->new('course', '/courses');
    $page->name = 'old-name';
    $page->title = 'Delivery Guide';

    if ($portugueseName !== '') {
        $page->setLanguageName('pt', $portugueseName);
    }

    if ($portugueseTitle !== null) {
        $page->setLanguageValue('pt', 'title', $portugueseTitle);
    }

    $page->save();

    return $page;
}

it('fills a cleared default name from the title', function (): void {
    $page = namedPage(null);

    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->set('names.default', '')
        ->call('suggestName', 'default')
        ->assertSet('names.default', 'delivery-guide');

    expect(lynx()->pages->get($page->id)?->name)->toBe('old-name');
});

it('gives the refilled default name to a language that copied the old one and has no title', function (): void {
    $page = namedPage(null, 'old-name');

    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->set('names.default', '')
        ->call('suggestName', 'default')
        ->assertSet('names.pt', 'delivery-guide');
});

it('keeps a name typed by hand in a language without a title when the default name is refilled', function (): void {
    $page = namedPage(null, 'nome-proprio');

    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->set('names.default', '')
        ->call('suggestName', 'default')
        ->assertSet('names.default', 'delivery-guide')
        ->assertSet('names.pt', 'nome-proprio');
});

it('leaves an empty name to follow the default when the default name is refilled', function (): void {
    $page = namedPage(null, '');

    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->set('names.default', '')
        ->call('suggestName', 'default')
        ->assertSet('names.pt', '');
});

it('keeps the name of a language with its own title when the default name is refilled', function (): void {
    $page = namedPage('Guia de Entrega');

    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->set('names.default', '')
        ->call('suggestName', 'default')
        ->assertSet('names.default', 'delivery-guide')
        ->assertSet('names.pt', 'nome-antigo');
});

it('fills a cleared name from that language\'s own title', function (): void {
    $page = namedPage('Guia de Entrega');

    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->set('names.pt', '')
        ->call('suggestName', 'pt')
        ->assertSet('names.pt', 'guia-de-entrega');
});

it('copies the default name when that language has no title of its own', function (): void {
    $page = namedPage(null);

    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->set('names.pt', '')
        ->call('suggestName', 'pt')
        ->assertSet('names.pt', 'old-name');
});

it('leaves a name that is not empty alone', function (): void {
    $page = namedPage('Guia de Entrega');

    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->set('names.pt', 'my-own')
        ->call('suggestName', 'pt')
        ->assertSet('names.pt', 'my-own');
});

it('ignores a language the site does not have', function (): void {
    $page = namedPage(null);

    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->call('suggestName', 'xx')
        ->assertSet('names.default', 'old-name')
        ->assertNotSet('names.xx', 'old-name');
});
