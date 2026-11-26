<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageChildren;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\Page;

/*
 * Before a page goes to the trash (or is deleted for good), the confirmation lists the pages
 * elsewhere that reference it, so the editor can fix those links first.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->courses = createCourses('alpha', 'beta', 'gamma', 'delta', 'epsilon');
});

function referenceAll(Page $target, Page ...$from): void
{
    foreach ($from as $page) {
        $page->related = [(int) $target->id];
        $page->save();
    }
}

function coursesTree(): mixed
{
    return Livewire::test(PageTree::class)->call('toggle', (int) lynx()->pages->get('/courses')?->id);
}

it('lists the referencing pages in the tree before trashing', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    referenceAll($alpha, $beta);

    coursesTree()->call('confirmDelete', $alpha->id)
        ->assertSeeHtml('data-referenced-by="1"')
        ->assertSeeHtml('data-referencing-page="'.$beta->id.'"');
});

it('shows no warning when nothing references the branch', function (): void {
    coursesTree()->call('confirmDelete', $this->courses['alpha']->id)
        ->assertDontSeeHtml('data-referenced-by=');
});

it('names a few referencing pages and counts the rest', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma, 'delta' => $delta, 'epsilon' => $epsilon] = $this->courses;
    referenceAll($alpha, $beta, $gamma, $delta, $epsilon);

    coursesTree()->call('confirmDelete', $alpha->id)
        ->assertSeeHtml('data-referenced-by="4"')
        ->assertSeeHtml('data-referencing-page="'.$delta->id.'"')
        ->assertDontSeeHtml('data-referencing-page="'.$epsilon->id.'"')
        ->assertSeeHtml('data-referenced-more="1"');
});

it('forgets the warning when the confirmation closes', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    referenceAll($alpha, $beta);

    coursesTree()->call('confirmDelete', $alpha->id)->call('cancelDelete')
        ->assertDontSeeHtml('data-referenced-by=');
});

it('lists the referencing pages in the Children tab before trashing', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = $this->courses;
    referenceAll($alpha, $gamma);
    referenceAll($beta, $gamma);

    Livewire::test(PageChildren::class, ['pageId' => (int) lynx()->pages->get('/courses/')->id])
        ->call('confirmTrash', [(int) $alpha->id, (int) $beta->id])
        ->assertSeeHtml('data-referenced-by="1"')
        ->assertSeeHtml('data-referencing-page="'.$gamma->id.'"');
});

it('leaves out pages that are trashed together', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    referenceAll($alpha, $beta);

    Livewire::test(PageChildren::class, ['pageId' => (int) lynx()->pages->get('/courses/')->id])
        ->call('confirmTrash', [(int) $alpha->id, (int) $beta->id])
        ->assertDontSeeHtml('data-referenced-by=');
});

/**
 * @param  list<string>  $permissions  granted on /courses/ only
 */
function coursesOnlyEditor(array $permissions): void
{
    $courses = (int) lynx()->pages->get('/courses/')->id;
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'courses-only', 'grants' => [
        ['permission' => 'admin.access'],
        ...array_map(fn (string $permission): array => ['permission' => $permission, 'branches' => [$courses]], $permissions),
    ]]);
    lynx()->run(AssignRoles::class, ['user' => '6', 'roles' => ['courses-only']]);
    test()->actingAs(new GenericUser(['id' => 6]));
}

it('counts referencing pages outside the user\'s view without naming them', function (): void {
    lynx()->templates->add('topic', fields: [['name' => 'title', 'required' => true], 'related']);
    $news = lynx()->pages->new('topic', '/');
    $news->name = 'news';
    $news->title = 'Newsroom';
    $news->related = [(int) $this->courses['alpha']->id];
    $news->save();
    coursesOnlyEditor(['pages.view', 'pages.delete']);

    Livewire::test(PageChildren::class, ['pageId' => (int) lynx()->pages->get('/courses/')->id])
        ->call('confirmTrash', [(int) $this->courses['alpha']->id])
        ->assertSeeHtml('data-referenced-by="1"')
        ->assertDontSeeHtml('data-referencing-page="'.$news->id.'"')
        ->assertDontSee('Newsroom');
});

it('refuses the trash confirmation to a user who may not trash the pages', function (): void {
    coursesOnlyEditor(['pages.view', 'pages.edit']);

    Livewire::test(PageChildren::class, ['pageId' => (int) lynx()->pages->get('/courses/')->id])
        ->call('confirmTrash', [(int) $this->courses['alpha']->id])
        ->assertForbidden();
});
