<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\LinkDialog;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Fields\HtmlCleaner;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\References\PageReference;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Support\Tables;

/*
 * Rich text links that name a page by id (ADR-0145): <a href="..." data-page="42">. Visitors get the
 * page's current address, or the text alone when they cannot open the page; the index lists them.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
});

function linkingBody(Page $page, string $html, string $language = 'default'): Page
{
    $page->setLanguageValue($language, 'body', $html);
    $page->save();

    return lynx()->pages->get($page->id);
}

/** The body as a template prints it: for visitors, or as the admin and commands read it. */
function renderedBody(Page $page, bool $inAdmin = false): string
{
    $render = fn (): string => trim(Blade::render('{{ $page->body }}', ['page' => $page]));

    return $inAdmin ? app(PageVisibility::class)->withoutRules($render) : app(PageVisibility::class)->asVisitor($render);
}

it('keeps a page id on links and drops anything else in data-page', function (): void {
    $cleaner = app(HtmlCleaner::class);

    expect($cleaner->clean('<p><a href="/x" data-page="42">X</a></p>'))->toBe('<p><a href="/x" data-page="42">X</a></p>')
        ->and($cleaner->clean('<p><a href="/x" data-page="4 2">X</a> <a href="/y" data-page="x">Y</a></p>'))->toBe('<p><a href="/x">X</a> <a href="/y">Y</a></p>')
        ->and($cleaner->clean('<p data-page="1">P</p>'))->toBe('<p>P</p>');
});

it('writes the page\'s current address, keeping a section', function (): void {
    $page = linkingBody($this->alpha, '<p><a href="/old/" data-page="'.$this->beta->id.'">Beta</a> and <a href="/old/#fees" data-page="'.$this->beta->id.'">fees</a></p>');
    $beta = lynx()->pages->get($this->beta->id);
    $beta->name = 'beta-2';
    $beta->save();

    expect(renderedBody(lynx()->pages->get($page->id)))
        ->toContain('href="/courses/beta-2"')
        ->toContain('href="/courses/beta-2#fees"');
});

it('writes the address in the language shown', function (): void {
    $beta = $this->beta;
    $beta->setLanguageName('pt', 'beta-pt', active: true);
    $beta->save();
    $page = linkingBody($this->alpha, '<p><a href="/x" data-page="'.$beta->id.'">Beta</a></p>', 'pt');

    expect(lynx()->language('pt', fn (): string => renderedBody($page)))->toContain('href="/pt/cursos/beta-pt"');
});

it('keeps only the text for visitors when the page is unpublished, trashed or gone', function (): void {
    $gamma = createCourses('gamma')['gamma'];
    $page = linkingBody($this->alpha, '<p><a href="/b" data-page="'.$this->beta->id.'">Beta</a>, <a href="/g" data-page="'.$gamma->id.'">Gamma</a>, <a href="/z" data-page="999999">Gone</a></p>');
    lynx()->pages->setStatus($this->beta, add: ['unpublished']);
    lynx()->pages->trash($gamma);

    expect(renderedBody(lynx()->pages->get($page->id)))->toBe('<p>Beta, Gamma, Gone</p>')
        ->and(renderedBody(lynx()->pages->get($page->id), inAdmin: true))->toContain('data-page="'.$this->beta->id.'"');
});

it('indexes rich text links as text references', function (): void {
    linkingBody($this->alpha, '<p><a href="/b" data-page="'.$this->beta->id.'">Beta</a></p>');

    expect(DB::table(Tables::PAGE_REFERENCES)->where('page_id', $this->alpha->id)->value('kind'))->toBe(PageReference::KIND_TEXT)
        ->and(lynx()->pages->backlinks($this->beta)->ids())->toBe([(int) $this->alpha->id]);
});

it('carries rich text links through a content package by page key', function (): void {
    linkingBody($this->alpha, '<p><a href="/b" data-page="'.$this->beta->id.'">Beta</a></p>', 'pt');
    $dir = sys_get_temp_dir().'/lynx-rich-links-'.uniqid();
    app(ContentExporter::class)->export($dir);
    $exported = File::get($dir.'/pages.jsonl');
    lynx()->pages->delete('/courses/');

    $this->artisan('lynx:import', ['path' => $dir])->assertSuccessful();
    File::deleteDirectory($dir);
    $alpha = lynx()->pages->get('/courses/alpha/');
    $beta = lynx()->pages->get('/courses/beta/');

    expect($exported)->not->toContain('data-page=\"'.$this->beta->id.'\"')
        ->and($alpha?->getUnformatted('body', 'pt'))->toContain('data-page="'.$beta?->id.'"');
});

it('sends the chosen page with the link from the link dialog', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $dialog = Livewire::test(LinkDialog::class)->call('open', requestId: 'r1', pageId: (int) $this->alpha->id, language: 'default');

    $dialog->call('choosePage', (int) $this->beta->id)->call('insert')
        ->assertDispatched('lynx-link-chosen', requestId: 'r1', href: '/courses/beta', page: (int) $this->beta->id);
    $dialog->call('open', requestId: 'r2', href: '/courses/beta', page: (string) $this->beta->id)->set('href', 'https://example.com/')->call('insert')
        ->assertDispatched('lynx-link-chosen', requestId: 'r2', href: 'https://example.com/', page: null);
});
