<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Editor\UnpublishedAncestors;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Core\Pages\Page;

/*
 * A page under a parent that is not published in a language has no URL of its own in that
 * language: its URL there is built from the parent's default one. The Settings tab says why
 * with a hint beside that language's URL, naming the parent.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function courseUnderCourses(): Page
{
    $page = lynx()->pages->new('course', '/courses');
    $page->name = 'delivery';
    $page->title = 'Delivery';
    $page->setLanguageName('pt', 'entrega');
    $page->save();

    return lynx()->pages->get($page->id);
}

function unpublishCoursesIn(string $code): void
{
    $courses = lynx()->pages->get('/courses/');
    $courses->setLanguageStatus($code, false);
    $courses->save();
}

it('names the nearest parent not published in each language', function (): void {
    $page = courseUnderCourses();
    unpublishCoursesIn('pt');

    expect(UnpublishedAncestors::of(lynx()->pages->get($page->id)))
        ->toBe(['pt' => (string) lynx()->pages->get('/courses/')?->title]);
});

it('names none while every parent is published', function (): void {
    expect(UnpublishedAncestors::of(courseUnderCourses()))->toBe([]);
});

it('explains the URL beside the language whose parent is not published', function (): void {
    $page = courseUnderCourses();
    unpublishCoursesIn('pt');
    $parent = (string) lynx()->pages->get('/courses/')?->title;

    Livewire::test(PageSettings::class, ['pageId' => $page->id])
        ->assertSeeHtml('data-url-parent-hint="pt"')
        ->assertSee("{$parent} is not published in Português");
});

it('shows no such hint while the parent is published', function (): void {
    Livewire::test(PageSettings::class, ['pageId' => courseUnderCourses()->id])
        ->assertDontSeeHtml('data-url-parent-hint');
});
