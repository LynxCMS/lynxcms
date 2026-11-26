<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\AdminSearch;
use LynxCms\Admin\Livewire\PagePicker;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

/*
 * The admin's search box and page pickers answer only through fields the user may view (ADR-0151,
 * ADR-0153): a selector or an index search on a hidden field would tell its value. Plain text then
 * searches titles only.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $alpha, 'beta' => $beta] = createCourses('alpha', 'beta');
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true]]]);
    $alpha->price = 500;
    $alpha->save();
    $beta->summary = 'A confidential remark';
    $beta->save();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'reader', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(SaveRole::class, ['name' => 'pricing', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->fields->update('price', ['settings' => ['access' => ['view' => ['pricing']]]]);
    lynx()->fields->update('summary', ['settings' => ['access' => ['view' => ['pricing']]]]);
    lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => ['reader']]);
    test()->actingAs(new GenericUser(['id' => 7]));
});

it('refuses a selector on a field the user may not view', function (): void {
    Livewire::test(AdminSearch::class)
        ->set('query', 'template=course, price>100')
        ->assertViewHas('error', fn (?string $error): bool => $error !== null)
        ->assertDontSee('Alpha');
});

it('searches titles only while a hidden field is in the search index', function (): void {
    Livewire::test(AdminSearch::class)->set('query', 'confidential')->assertDontSee('Beta');
    Livewire::test(AdminSearch::class)->set('query', 'Alpha')->assertSee('Alpha');
});

it('keeps page pickers to titles while a hidden field is in the search index', function (): void {
    Livewire::test(PagePicker::class)->set('search', 'confidential')->assertDontSee('Beta');
    Livewire::test(PagePicker::class)->set('search', 'Alp')->assertSee('Alpha');
});
