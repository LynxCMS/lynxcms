<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\PageVisibility;

/*
 * A page reference shows visitors only pages they could open: an unpublished or trashed page an
 * editor still references never leaks its title or URL on the site. The admin sees them all.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $alpha, 'soon' => $soon, 'old' => $old, 'beta' => $beta] = createCourses('alpha', 'soon', 'old', 'beta');
    lynx()->pages->setStatus($soon, add: ['unpublished']);
    $alpha->related = [$beta->id, $soon->id, $old->id];
    $alpha->save();
    lynx()->pages->trash($old);
    Route::get('related-titles', fn (): string => implode(',', lynx()->pages->get('/courses/alpha/')?->related->map(fn (Page $page): string => (string) $page->title) ?? []))->middleware('web');
});

it('leaves unpublished and trashed pages out of references on the site', function (): void {
    $this->get('/related-titles')->assertOk()->assertSeeText('Beta')->assertDontSeeText('Soon')->assertDontSeeText('Old');
});

it('keeps every referenced page for the admin and commands', function (): void {
    $titles = app(PageVisibility::class)->withoutRules(fn (): array => lynx()->pages->get('/courses/alpha/')?->related->map(fn (Page $page): string => (string) $page->title) ?? []);

    expect($titles)->toBe(['Beta', 'Soon', 'Old']);
});
