<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageCreator;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    $this->actingAs(new GenericUser(['id' => 1]));
});

function coursesId(): int
{
    return (int) lynx()->pages->get('/courses')->id;
}

it('adds New to the page tree', function (): void {
    Livewire::test(PageTree::class)
        ->assertSeeHtml('href="'.route('lynx.admin.pages.create', ['parent' => coursesId()]).'"');
});

it('leaves New out for pages whose template allows no children', function (): void {
    lynx()->templates->update('course', ['allow_children' => false]);
    $alpha = (int) lynx()->pages->get('/courses/alpha')->id;

    Livewire::test(PageTree::class)
        ->set('expanded', [(int) lynx()->pages->get('/')->id, coursesId()])
        ->assertSeeHtml('href="'.route('lynx.admin.pages.create', ['parent' => coursesId()]).'"')
        ->assertDontSeeHtml('href="'.route('lynx.admin.pages.create', ['parent' => $alpha]).'"');

    $this->get(route('lynx.admin.pages.create', ['parent' => $alpha]))->assertNotFound();
});

it('opens the create screen for a parent', function (): void {
    $this->get(route('lynx.admin.pages.create', ['parent' => coursesId()]))
        ->assertOk()
        ->assertSeeLivewire(PageCreator::class)
        ->assertSee('New page under Courses');
});

it('returns 404 without a valid parent', function (string $query): void {
    $this->get('/lynx/pages/create'.$query)->assertNotFound();
})->with(['', '?parent=9999', '?parent=abc']);

it('keeps refused users out', function (): void {
    Gate::define('accessLynxAdmin', fn (): bool => false);

    $this->get(route('lynx.admin.pages.create', ['parent' => coursesId()]))->assertForbidden();
});

it('preselects the template most used under the parent and offers the others', function (): void {
    lynx()->templates->add('notice', fields: ['title'], singleton: true);
    $notice = lynx()->pages->new('notice', '/');
    $notice->name = 'notice';
    $notice->title = 'Notice';
    $notice->save();
    lynx()->menus()->create('top', 'Top');

    Livewire::test(PageCreator::class, ['parentId' => coursesId()])
        ->assertSet('template', 'course')
        ->assertSeeHtml('value="courses"')
        ->assertDontSeeHtml('value="notice"')
        ->assertDontSeeHtml('value="menu-item"');
});

it('asks for the title and the template\'s other required fields', function (): void {
    lynx()->templates->add('event', fields: [['name' => 'title', 'required' => true], ['name' => 'price', 'required' => true], 'summary']);

    Livewire::test(PageCreator::class, ['parentId' => coursesId()])
        ->set('template', 'event')
        ->assertSeeHtml('wire:model.live.debounce.400ms="values.title.default"')
        ->assertSeeHtml('wire:model.live.debounce.400ms="values.price.default"')
        ->assertDontSeeHtml('values.summary.default');
});

it('previews the URL from the title, or from a name typed by hand', function (): void {
    Livewire::test(PageCreator::class, ['parentId' => coursesId()])
        ->assertSeeHtml('wire:model.live.debounce.400ms="values.title.default"')
        ->set('values.title.default', 'Agile Leadership: Ação!')
        ->assertSee('/courses/agile-leadership-acao')
        ->set('name', 'leaders')
        ->assertSee('/courses/leaders');
});

it('creates an unpublished page and opens it in the editor', function (): void {
    $creator = Livewire::test(PageCreator::class, ['parentId' => coursesId()])
        ->set('values.title.default', 'Agile Leadership')
        ->set('values.title.pt', 'Liderança Ágil')
        ->call('create')
        ->assertHasNoErrors();

    $page = lynx()->pages->findOne('name=agile-leadership, include=all');
    $creator->assertRedirect(route('lynx.admin.pages.edit', $page->id));

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->parentId)->toBe(coursesId())
        ->and($page->template()->name)->toBe('course')
        ->and($page->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue()
        ->and(lynx()->language('pt', fn (): string => (string) $page->title))->toBe('Liderança Ágil');
});

it('publishes right away when asked', function (): void {
    Livewire::test(PageCreator::class, ['parentId' => coursesId()])
        ->set('values.title.default', 'Live course')
        ->set('publish', true)
        ->call('create');

    expect(lynx()->pages->findOne('name=live-course')?->hasStatus(PageStatus::UNPUBLISHED))->toBeFalse();
});

it('shows core errors for a missing title or a name already taken', function (): void {
    Livewire::test(PageCreator::class, ['parentId' => coursesId()])
        ->call('create')
        ->assertHasErrors('values.title')
        ->set('values.title.default', 'Alpha')
        ->call('create')
        ->assertHasErrors('names.default');
});

it('refuses a template the screen does not offer', function (): void {
    lynx()->menus()->create('top', 'Top');

    Livewire::test(PageCreator::class, ['parentId' => coursesId()])
        ->set('template', 'menu-item')
        ->set('values.title.default', 'Sneaky')
        ->call('create')
        ->assertHasErrors('template');

    expect(lynx()->pages->findOne('name=sneaky, include=all'))->toBeNull();
});
