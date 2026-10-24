<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\NotFoundPageForm;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Pages\SetNotFoundPage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Lynx;
use LynxCms\Core\Models\Setting;
use LynxCms\Core\Pages\NotFoundPage;
use LynxCms\Core\Pages\PageStatus;

/*
 * The page not found (ADR-0085): a page in the tree, written by editors, shown with status 404
 * for front-end URLs that match nothing. lynx:install creates one; superusers pick another, or none.
 */

beforeEach(fn (): Lynx => courseSite());

function notFoundPage(): NotFoundPage
{
    return app(NotFoundPage::class);
}

it('is created by install: a system page with a template of its own', function (): void {
    $page = notFoundPage()->page();

    expect($page?->id)->toBe(lynx()->pages->get('/not-found/')?->id)
        ->and($page?->template()->name)->toBe('not-found')
        ->and($page?->hasStatus(PageStatus::SYSTEM))->toBeTrue()
        ->and($page?->hasStatus(PageStatus::HIDDEN))->toBeTrue()
        ->and(lynx()->pages->find('template=not-found')->count())->toBe(0);
});

it('answers a URL that matches nothing with the page, status 404 and noindex', function (): void {
    $page = notFoundPage()->page();
    $page->title = 'Lost in the reeds';
    $page->save();

    $this->get('/nothing-here')->assertNotFound()->assertSee('<h1>Lost in the reeds</h1>', false)->assertHeader('X-Robots-Tag', 'noindex');
    $this->get('/courses/nothing-here')->assertNotFound()->assertSee('<h1>Lost in the reeds</h1>', false);
});

it('answers in the visitor\'s language', function (): void {
    $page = notFoundPage()->page();
    $page->setLanguageValue('pt', 'title', 'Perdido');
    $page->save();

    $this->get('/pt/nada')->assertNotFound()->assertSee('<h1>Perdido</h1>', false);
});

it('answers its own URL with status 404 too', function (): void {
    $this->get('/not-found')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex')->assertSee('<h1>Page not found</h1>', false);
});

it('leaves JSON, other methods and sites without one to Laravel', function (): void {
    $this->getJson('/nothing-here')->assertNotFound()->assertJsonMissing(['Page not found']);
    $this->post('/nothing-here')->assertStatus(405);

    lynx()->run(SetNotFoundPage::class, ['page' => null]);

    $this->get('/nothing-here')->assertNotFound()->assertDontSee('<h1>Page not found</h1>', false);
});

it('falls back to Laravel\'s 404 when the page is trashed or unpublished', function (): void {
    lynx()->run(SetNotFoundPage::class, ['page' => '/courses/']);
    lynx()->pages->setStatus('/courses/', ['unpublished']);

    expect(notFoundPage()->page())->toBeNull();
    $this->get('/nothing-here')->assertNotFound()->assertDontSee('<h1>Courses</h1>', false);
});

it('falls back to Laravel\'s 404 when the page fails to render, and reports why', function (): void {
    $views = testingPath('broken-views');
    File::ensureDirectoryExists("{$views}/templates");
    File::put("{$views}/templates/not-found.blade.php", '{{ throw new RuntimeException("Broken template") }}');
    View::getFinder()->prependLocation($views);

    $this->get('/nothing-here')->assertNotFound()->assertDontSee('Broken template')->assertDontSee('<h1>Page not found</h1>', false);
});

it('picks another page, or none', function (): void {
    $courses = lynx()->pages->get('/courses/')?->id;

    expect(lynx()->run(SetNotFoundPage::class, ['page' => '/courses/'])->value?->id)->toBe($courses)
        ->and(notFoundPage()->page()?->id)->toBe($courses);

    lynx()->run(SetNotFoundPage::class, ['page' => null]);

    expect(notFoundPage()->id())->toBeNull()->and(notFoundPage()->isChosen())->toBeTrue();
});

it('refuses the home page, a trashed page and an unknown one', function (mixed $page): void {
    if ($page === 'trashed') {
        createCourses('alpha');
        lynx()->pages->trash('/courses/alpha/');
        $page = (string) lynx()->pages->get('/courses/alpha/')?->id;
    }

    expect(fn (): ActionResult => lynx()->run(SetNotFoundPage::class, ['page' => $page]))->toThrow(ValidationException::class);
})->with(['the home page' => '/', 'a trashed page' => 'trashed', 'an unknown page' => '/nowhere/']);

it('changes nothing on a dry run or when it rolls back', function (): void {
    $id = notFoundPage()->id();
    $result = lynx()->run(SetNotFoundPage::class, ['page' => '/courses/'], dryRun: true);

    expect($result->changes->all())->not->toBeEmpty()->and(notFoundPage()->id())->toBe($id);

    lynx()->hook(SetNotFoundPage::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn (): ActionResult => lynx()->run(SetNotFoundPage::class, ['page' => '/courses/']))->toThrow(ActionHalted::class)
        ->and(notFoundPage()->id())->toBe($id);
});

it('is not made again by install once someone chose a page or none', function (): void {
    lynx()->run(SetNotFoundPage::class, ['page' => null]);
    installLynx();

    expect(notFoundPage()->id())->toBeNull()
        ->and(Setting::query()->whereKey(NotFoundPage::SETTING)->exists())->toBeTrue();
});

it('is closed to anyone but superusers', function (): void {
    expect(SetNotFoundPage::permission())->toBeNull();
});

it('marks the page in the admin\'s tree, and superusers pick it under Settings > General', function (): void {
    $user = new GenericUser(['id' => 1]);

    Livewire::actingAs($user)->test(PageTree::class)->assertSeeHtml('data-tree-marker="not-found"');
    $this->actingAs($user)->get('/lynx/general')->assertOk()->assertSeeLivewire(NotFoundPageForm::class);

    $courses = (int) lynx()->pages->get('/courses/')?->id;
    Livewire::actingAs($user)->test(NotFoundPageForm::class)->assertSet('page', [notFoundPage()->id()])
        ->set('page', [$courses])->call('save')->assertHasNoErrors();

    expect(notFoundPage()->id())->toBe($courses);

    Livewire::actingAs($user)->test(NotFoundPageForm::class)->set('page', [])->call('save');

    expect(notFoundPage()->id())->toBeNull();
});
