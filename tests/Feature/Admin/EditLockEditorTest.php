<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Actions\Roles\AssignRoles;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => 'x'],
        ['id' => 2, 'name' => 'Rui Costa', 'email' => 'rui@example.com', 'password' => 'x'],
    ]);
});

function alphaPageId(): int
{
    return (int) lynx()->pages->get('/courses/alpha/')?->id;
}

function openEditorAs(int $user): mixed
{
    test()->actingAs(new GenericUser(['id' => $user]));

    return Livewire::test(PageEditor::class, ['pageId' => alphaPageId()]);
}

it('takes the edit lock when the editor opens, and renews it every minute', function (): void {
    openEditorAs(1)->assertSeeHtml('wire:poll.60s="renewEditLock"');

    expect(lynx()->pages->editLock(alphaPageId())['holder'])->toBe('user:1');
});

it('opens read only for a second editor, saying who is editing', function (): void {
    openEditorAs(1);

    openEditorAs(2)
        ->assertSee('Ana Lima is editing this page')
        ->assertDontSeeHtml('data-lynx-save');
});

it('becomes editable once the other editor has gone', function (): void {
    openEditorAs(1);
    $second = openEditorAs(2);
    $this->travel(config('lynx.edit_lock_seconds') + 1)->seconds();

    $second->call('renewEditLock')->assertSeeHtml('data-lynx-save')->assertDontSee('is editing this page');
});

it('lets a superuser take over editing', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['editor']]);
    openEditorAs(1);

    openEditorAs(2)->assertSeeHtml('wire:click="takeOverEditLock"')
        ->call('takeOverEditLock')
        ->assertSeeHtml('data-lynx-save');

    expect(lynx()->pages->editLock(alphaPageId())['holder'])->toBe('user:2');
});
