<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\EditorLinks;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\PagePassword\Access\PasswordVisibility;
use LynxCms\PagePassword\Access\Unlocks;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\Admin\PagePasswordSettings;
use LynxCms\PagePassword\PagePasswordSchema;

/*
 * Step 4 of documentation/plans/page-passwords.md: the sitemap, menus, the page tree, the page
 * editor and the Settings screen.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    lynx()->templates->update('course', ['allow_children' => true]);
    ['open' => $this->open, 'members' => $this->members] = createCourses('open', 'members');
    $this->members->{PagePasswordSchema::FIELD} = 'open sesame';
    $this->members->save();
    $child = lynx()->pages->new('course', '/courses/members');
    $child->name = 'inside';
    $child->title = 'Inside';
    $child->save();
    $this->child = $child;
});

it('leaves protected pages out of the sitemap, even for signed-in editors', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $xml = (string) $this->actingAs(new GenericUser(['id' => 1]))->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('/courses/open')->not->toContain('/courses/members');
});

it('leaves menu items of locked pages out, and shows them once unlocked', function (): void {
    lynx()->menus()->create('top', 'Top');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses/open']);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses/members']);
    $labels = fn (): array => array_map(fn ($item): string => $item->label, lynx()->menus()->get('top')?->items ?? []);

    expect($labels())->toBe(['Open']);

    request()->cookies->set(Unlocks::COOKIE, app(Unlocks::class)->cookieValue([(int) $this->members->id]));
    app(PasswordVisibility::class)->flush();

    expect($labels())->toBe(['Open', 'Members']);
});

it('marks protected pages in the page tree', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageTree::class, ['root' => '/courses'])
        ->assertSeeHtml('data-tree-marker="page-password"')
        ->assertSee('Password protected');
});

it('links a page to the password that protects it from above', function (): void {
    $links = app(EditorLinks::class)->for(lynx()->pages->get($this->child->id));

    expect($links)->toContain(['label' => 'Protected by Members', 'url' => route('lynx.admin.pages.edit', $this->members->id)])
        ->and(app(EditorLinks::class)->for(lynx()->pages->get($this->open->id)))->toBe([]);
});

it('turns passwords on and off per template, and lists protected pages', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->get('/lynx/page-passwords')->assertOk()->assertSee('Members')->assertSee('1 page below')->assertSeeLivewire(PagePasswordSettings::class);

    Livewire::test(PagePasswordSettings::class)
        ->call('disable', 'course')
        ->assertHasNoErrors();
    expect(lynx()->templates->fields('course')->has(PagePasswordSchema::FIELD))->toBeFalse();

    Livewire::test(PagePasswordSettings::class)->call('enable', 'course');
    expect(lynx()->templates->fields('course')->has(PagePasswordSchema::FIELD))->toBeTrue();
});

it('lists the screen under Settings', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);

    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/pages')->assertSeeInOrder(['Settings', 'Sitemap', 'Page passwords', 'Languages']);
});
