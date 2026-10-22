<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\PagePassword\Access\Unlocks;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\PagePasswordSchema;

/*
 * A password on a page locks it and the pages below it for visitors (documentation/plans/page-passwords.md):
 * left out of selectors and page reference fields until unlocked, the nearest password winning.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    lynx()->templates->update('course', ['allow_children' => true]);
    ['open' => $this->open, 'members' => $this->members] = createCourses('open', 'members');

    $child = lynx()->pages->new('course', '/courses/members/');
    $child->name = 'inherits';
    $child->title = 'Inherits';
    $child->save();
    $own = lynx()->pages->new('course', '/courses/members/');
    $own->name = 'own';
    $own->title = 'Own';
    $own->{PagePasswordSchema::FIELD} = 'second';
    $own->save();
    $this->own = $own;

    $this->members->{PagePasswordSchema::FIELD} = 'first';
    $this->members->save();

    Route::get('visible', fn (): string => implode(',', lynx()->pages->find('template=course, sort=name')->map(fn (Page $page): string => (string) $page->name)))->middleware('web');
});

/** The cookie value that unlocks the given pages, as the unlock form will set it. */
function unlockCookie(Page ...$pages): string
{
    return app(Unlocks::class)->cookieValue(array_map(fn (Page $page): int => (int) $page->id, $pages));
}

it('hides a protected page and the pages below it from visitors', function (): void {
    $this->get('/visible')->assertOk()->assertSeeText('open')->assertDontSeeText('members')->assertDontSeeText('inherits')->assertDontSeeText('own');
});

it('shows what an unlocked password opens, but not a nearer password', function (): void {
    $this->withCookie(Unlocks::COOKIE, unlockCookie($this->members))
        ->get('/visible')->assertSeeText('inherits')->assertSeeText('members')->assertDontSeeText('own');

    $this->withCookie(Unlocks::COOKIE, unlockCookie($this->members, $this->own))
        ->get('/visible')->assertSeeText('own');
});

it('locks again when the password changes', function (): void {
    $cookie = unlockCookie($this->members);
    $this->members->{PagePasswordSchema::FIELD} = 'changed';
    $this->members->save();

    $this->withCookie(Unlocks::COOKIE, $cookie)->get('/visible')->assertDontSeeText('members');
});

it('drops locked pages from page reference fields', function (): void {
    $this->open->related = [$this->members->id];
    $this->open->save();
    Route::get('related', fn (): string => (string) count(lynx()->pages->get('/courses/open/')?->related ?? []))->middleware('web');

    $this->get('/related')->assertSee('0');
    $this->withCookie(Unlocks::COOKIE, unlockCookie($this->members))->get('/related')->assertSee('1');
});

it('lets signed-in users through to the pages they may view in the admin', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);

    $this->actingAs(new GenericUser(['id' => 1]))->get('/visible')->assertSeeText('members')->assertSeeText('own');
});

it('keeps pages locked for signed-in users who may not view them', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'courses-only', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view', 'branches' => [$this->open->id]]]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['courses-only']]);

    $this->actingAs(new GenericUser(['id' => 2]))->get('/visible')->assertDontSeeText('members');
});

it('changes nothing for sites without passwords', function (): void {
    $this->members->{PagePasswordSchema::FIELD} = '';
    $this->members->save();
    $this->own->{PagePasswordSchema::FIELD} = '';
    $this->own->save();

    $this->get('/visible')->assertSeeText('members')->assertSeeText('own');
});

it('counts visitors who unlocked nothing as the default visitor, so their work is shared', function (): void {
    Route::get('default-visitor', fn (): string => app(PageVisibility::class)->seesDefault() ? 'default' : 'apart')->middleware('web');

    $this->get('/default-visitor')->assertSeeText('default');
    $this->withCookie(Unlocks::COOKIE, unlockCookie($this->members))->get('/default-visitor')->assertSeeText('apart');
});
