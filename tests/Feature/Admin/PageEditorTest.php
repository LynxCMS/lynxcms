<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function course(): Page
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'delivery-8';
    $page->title = 'Navigating Project Delivery';
    $page->summary = 'An in-depth course.';
    $page->body = '<p>Body with <script>alert(1)</script></p>';
    $page->price = 249;
    $page->related = [(int) lynx()->pages->get('/courses/')->id];
    $page->setLanguageName('pt', 'navegando-entrega-8');
    $page->setLanguageValue('pt', 'title', 'Navegando pela Entrega de Projetos');
    $page->save();

    return $page;
}

function editUrl(Page $page): string
{
    return "/lynx/pages/{$page->id}/edit";
}

it('opens the editor for a page', function (): void {
    $page = course();

    $this->get(editUrl($page))
        ->assertOk()
        ->assertSeeLivewire(PageEditor::class)
        ->assertSee('Navigating Project Delivery');
});

it('points the top bar view link at the edited page', function (): void {
    $page = course();

    $this->get(editUrl($page))
        ->assertSeeInOrder(['href="'.$page->url().'"', 'target="_blank"', 'aria-label="View page"'], false);
});

it('keeps guests and refused users out', function (): void {
    $page = course();
    auth()->forgetUser();
    $this->getJson(editUrl($page))->assertUnauthorized();

    $this->actingAs(new GenericUser(['id' => 1]));
    Gate::define('accessLynxAdmin', fn (): bool => false);
    $this->get(editUrl($page))->assertForbidden();
});

it('returns 404 for a missing page', function (): void {
    $this->get('/lynx/pages/9999/edit')->assertNotFound();
});

it('shows the breadcrumb, template and every field of the template', function (): void {
    $page = course();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeInOrder(['Home', 'Courses', 'Navigating Project Delivery'])
        ->assertSee('course')
        ->assertSeeInOrder(['Title', 'Summary', 'Body', 'Price', 'Related']);
});

it('shows translatable values per language and where a value falls back', function (): void {
    $page = course();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSet('values.title.pt', 'Navegando pela Entrega de Projetos')
        ->assertSet('values.summary.pt', '')
        ->assertSeeHtml('placeholder="An in-depth course."')
        ->assertSee('visitors see the Default value');
});

it('shows shared values once and page references by title', function (): void {
    $page = course();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSet('values.price.default', '249')
        ->assertSeeHtml('href="'.route('lynx.admin.pages.edit', lynx()->pages->get('/courses/')->id).'"');
});

it('never outputs stored HTML unescaped, and scripts never get stored', function (): void {
    $page = course();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertDontSeeHtml('&lt;script&gt;');

    expect((string) lynx()->pages->get($page->id)->getLanguageValue('default', 'body'))->not->toContain('script');
});

it('shows a locked page with a way to unlock it', function (): void {
    $page = course();
    lynx()->pages->setStatus($page, add: ['locked']);

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSee('This page is locked')
        ->call('unlock')
        ->assertDontSee('This page is locked');

    expect(lynx()->pages->get($page->id)->hasStatus(PageStatus::LOCKED))->toBeFalse();
});

it('links Edit in the page tree to the editor', function (): void {
    $page = course();

    Livewire::test(PageTree::class, ['root' => '/courses/'])
        ->assertSeeHtml('href="'.route('lynx.admin.pages.edit', $page->id).'"');
});

it('shows compact, uppercase language tabs', function (): void {
    $page = lynx()->pages->get('/courses/delivery/') ?? createCourses('compact')['compact'];

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('data-variant="compact"')
        ->assertSeeHtml('text-xxs');
});
