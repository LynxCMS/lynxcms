<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Admin\Tree\TreeDetails;
use LynxCms\Core\Pages\Page;

/*
 * The page tree's Details view: beside each title, the page's state in every language, its
 * status and its last change. Simple, the default, shows titles only. Each user's choice is kept
 * for the session. Other trees built on PageTree (the menu builder) stay simple.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

/** @return array{own: Page, follows: Page, missing: Page} */
function languageStates(): array
{
    ['own' => $own, 'follows' => $follows, 'missing' => $missing] = createCourses('own', 'follows', 'missing');
    $own->setLanguageName('pt', 'proprio');
    $own->setLanguageValue('pt', 'title', 'Próprio');
    $own->save();
    $follows->setLanguageName('pt', 'segue');
    $follows->save();

    return ['own' => $own, 'follows' => $follows, 'missing' => $missing];
}

it('says, per language, whether a page has its own title, shows another language\'s, or is not there', function (): void {
    $pages = languageStates();
    $states = fn (Page $page): array => array_column((new TreeDetails)->for($page)['languages'], 'state', 'code');

    expect($states($pages['own']))->toBe(['default' => 'own', 'pt' => 'own'])
        ->and($states($pages['follows']))->toBe(['default' => 'own', 'pt' => 'follows'])
        ->and($states($pages['missing']))->toBe(['default' => 'own', 'pt' => 'missing']);
});

it('lists no languages on a site with one', function (): void {
    lynx()->languages->delete('pt');

    expect((new TreeDetails)->for(createCourses('alpha')['alpha'])['languages'])->toBe([]);
});

it('shows the simple tree by default, and the details when asked', function (): void {
    languageStates();
    $courses = (int) lynx()->pages->get('/courses/')->id;

    Livewire::test(PageTree::class)
        ->set('expanded', [(int) lynx()->pages->get('/')->id, $courses])
        ->assertSeeHtml('data-tree-view')
        ->assertDontSeeHtml('data-tree-details')
        ->call('showDetails', true)
        ->assertSeeHtml('data-tree-details')
        ->assertSeeHtml('data-language-state="follows"')
        ->assertSeeHtml('data-language-state="missing"');
});

it('keeps the choice for the session', function (): void {
    Livewire::test(PageTree::class)->call('showDetails', true);

    Livewire::test(PageTree::class)->assertSeeHtml('data-tree-details');
});

it('keeps other trees simple', function (): void {
    Livewire::test(PageTree::class, ['events' => 'menu'])
        ->assertDontSeeHtml('data-tree-view')
        ->call('showDetails', true)
        ->assertDontSeeHtml('data-tree-details');
});
