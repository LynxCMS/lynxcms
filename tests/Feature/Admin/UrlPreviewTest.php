<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LynxCms\Admin\Editor\UrlPreviews;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\PathBuilder;
use LynxCms\Core\Support\Tables;

/*
 * The Settings tab previews each language's URL with the rule saving uses (ADR-0011): the
 * language's prefix, then every parent's name in that language, or its default name when the
 * parent has no version in it. A hint beside the URL names the nearest parent that lends its
 * default name, and says to publish it in that language.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function previewedCourse(): Page
{
    $page = lynx()->pages->new('course', '/courses');
    $page->name = 'delivery';
    $page->title = 'Delivery';
    $page->setLanguageName('pt', 'entrega');
    $page->save();

    return lynx()->pages->get($page->id);
}

/** As on a site where the parent was never published in Portuguese: no row for it at all. */
function coursesWithoutPortuguese(): Page
{
    $courses = lynx()->pages->get('/courses/');
    DB::table(Tables::PAGE_LANGUAGES)->where('page_id', $courses->id)->where('language_id', lynx()->languages->get('pt')->id)->delete();
    app(PathBuilder::class)->rebuild((int) $courses->id);
    app(PageLoader::class)->flush();

    return lynx()->pages->get('/courses/');
}

it('previews the URL saving gives, with the language prefix, when a parent has no version in it', function (): void {
    $page = previewedCourse();
    $courses = coursesWithoutPortuguese();
    $page = lynx()->pages->get($page->id);

    expect(new UrlPreviews($page)->urls(['default' => 'delivery', 'pt' => 'entrega']))
        ->toMatchArray(['pt' => '/pt/'.$courses->name('default').'/entrega'])
        ->and($page->localUrl('pt'))->toBe('/pt/'.$courses->name('default').'/entrega');
});

it('does not say the URL will redirect when it stays the same', function (): void {
    $page = previewedCourse();
    coursesWithoutPortuguese();

    Livewire::test(PageSettings::class, ['pageId' => $page->id])->assertDontSeeHtml('data-url-will-redirect');
});

it('names the parent that lends its default name, beside that language\'s URL', function (): void {
    $page = previewedCourse();
    $courses = coursesWithoutPortuguese();

    expect(new UrlPreviews(lynx()->pages->get($page->id))->borrowedFrom())->toBe(['pt' => (string) $courses->title]);
    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->assertSeeHtml('data-url-parent-hint="pt"')
        ->assertSee("{$courses->title} page is not published in Português");
});

it('keeps an unpublished parent\'s own name in the URL, with no hint', function (): void {
    $page = previewedCourse();
    $courses = lynx()->pages->get('/courses/');
    $courses->setLanguageStatus('pt', false);
    $courses->save();
    $page = lynx()->pages->get($page->id);

    expect(new UrlPreviews($page)->urls(['default' => 'delivery', 'pt' => 'entrega'])['pt'])->toBe($page->localUrl('pt'))
        ->and(new UrlPreviews($page)->borrowedFrom())->toBe([]);
});

it('previews a renamed page\'s new URL, and says the old one will redirect', function (): void {
    $page = previewedCourse();

    expect(new UrlPreviews($page)->urls(['default' => 'delivery', 'pt' => 'nova'])['pt'])->toEndWith('/nova');
    Livewire::test(PageSettings::class, ['pageId' => $page->id])->set('names.pt', 'nova')->assertSeeHtml('data-url-will-redirect');
});

it('names no parent while every parent has a version in each language', function (): void {
    expect(new UrlPreviews(previewedCourse())->borrowedFrom())->toBe([]);
});
