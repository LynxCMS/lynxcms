<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageRevisions;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

beforeEach(function (): void {
    courseSite();
    $alpha = createCourses('alpha')['alpha'];
    $alpha->title = 'Alpha two';
    $alpha->save();
    DB::table('users')->insert(['id' => 1, 'name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => 'x']);
    test()->actingAs(new GenericUser(['id' => 1]));
});

function revisionsPage(): int
{
    return (int) lynx()->pages->get('/courses/alpha/')?->id;
}

it('lists the page\'s revisions in the settings', function (): void {
    Livewire::test(PageSettings::class, ['pageId' => revisionsPage()])->assertSee('Revisions');

    Livewire::test(PageRevisions::class, ['pageId' => revisionsPage()])
        ->assertSee('Console')
        ->assertSeeHtml('wire:click="compare(');
});

it('compares a revision with the page, field by field', function (): void {
    $first = lynx()->pages->revisions(revisionsPage())[1]['id'];

    Livewire::test(PageRevisions::class, ['pageId' => revisionsPage()])
        ->call('compare', $first)
        ->assertSee(['Title', 'Alpha two', 'Alpha']);
});

it('restores a revision', function (): void {
    $first = lynx()->pages->revisions(revisionsPage())[1]['id'];

    Livewire::test(PageRevisions::class, ['pageId' => revisionsPage()])
        ->call('restore', $first)
        ->assertDispatched('lynx-page-changed')
        ->assertDispatched('lynx-notify', message: 'Revision restored.');

    expect((string) lynx()->pages->get(revisionsPage())?->get('title'))->toBe('Alpha');
});

it('offers Restore only to users who may edit the page', function (): void {
    lynx()->actingAs(Actor::cli(), function (): void {
        lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['superuser']]);
        lynx()->run(SaveRole::class, ['name' => 'viewer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
        lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['viewer']]);
    });

    Livewire::test(PageRevisions::class, ['pageId' => revisionsPage()])
        ->assertSeeHtml('wire:click="compare(')
        ->assertDontSeeHtml('wire:click="restore(');
});
