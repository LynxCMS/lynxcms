<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Admin\Livewire\PageTree;

/*
 * Duplicate from the page tree's More menu and from the Settings tab: both run pages.duplicate
 * and open the copy in the editor.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    createCourses('alpha');
    $week = lynx()->pages->new('course', '/courses/alpha/');
    $week->name = 'week-1';
    $week->title = 'Week 1';
    $week->save();
});

function duplicatedAlphaId(): int
{
    return (int) lynx()->pages->get('/courses/alpha/')->id;
}

it('duplicates a page from the tree and opens the copy', function (): void {
    $tree = Livewire::test(PageTree::class)->call('duplicate', duplicatedAlphaId());
    $copy = lynx()->pages->get('/courses/alpha-copy/');

    $tree->assertRedirect(route('lynx.admin.pages.edit', $copy->id));
    expect($copy->children('include=all')->count())->toBe(0);
});

it('duplicates a branch from the tree', function (): void {
    Livewire::test(PageTree::class)->call('duplicate', duplicatedAlphaId(), true);

    expect(lynx()->pages->get('/courses/alpha-copy/')->children('include=all')->count())->toBe(1);
});

it('shows why the tree cannot duplicate the home page', function (): void {
    Livewire::test(PageTree::class)->call('duplicate', (int) lynx()->pages->get('/')->id)->assertSet('error', fn (?string $error): bool => $error !== null)->assertNoRedirect();
});

it('duplicates the page from the Settings tab, with its children when ticked', function (): void {
    $settings = Livewire::test(PageSettings::class, ['pageId' => duplicatedAlphaId()])->set('duplicateChildren', true)->call('duplicate');
    $copy = lynx()->pages->get('/courses/alpha-copy/');

    $settings->assertRedirect(route('lynx.admin.pages.edit', $copy->id));
    expect($copy->children('include=all')->count())->toBe(1);
});

it('marks the copy\'s title as a copy in the admin\'s language', function (): void {
    Livewire::test(PageTree::class)->call('duplicate', duplicatedAlphaId());
    Livewire::test(PageSettings::class, ['pageId' => duplicatedAlphaId()])->call('duplicate');

    $prefix = __('lynx-admin::pages.copy_prefix');
    expect((string) lynx()->pages->get('/courses/alpha-copy/')->title)->toBe($prefix.' Alpha')
        ->and((string) lynx()->pages->get('/courses/alpha-copy-2/')->title)->toBe($prefix.' Alpha');
});
