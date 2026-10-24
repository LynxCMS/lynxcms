<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\LinkDialog;
use LynxCms\Admin\Livewire\PagePicker;
use LynxCms\Admin\Livewire\PageTableInput;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

/*
 * Page pickers read pages for the editor, so a crafted request must not make them show pages
 * outside what the editor may view. Pages the field already held when it opened still show,
 * so a value the editor can't see doesn't look empty and get dropped on save.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    $secret = lynx()->pages->new('courses', '/');
    $secret->name = 'secret';
    $secret->title = 'Secret plans';
    $secret->save();
    $this->secretId = (int) $secret->id;
    $this->alphaId = (int) lynx()->pages->get('/courses/alpha/')?->id;

    $branch = ['branches' => [(int) lynx()->pages->get('/courses/')?->id]];
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'course-editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view'] + $branch, ['permission' => 'pages.edit'] + $branch]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['course-editor']]);
    $this->actingAs(new GenericUser(['id' => 5]));
});

it('keeps the page table to pages the editor may view, or that the field held', function (): void {
    Livewire::test(PageTableInput::class, ['value' => [], 'pageId' => $this->alphaId])
        ->set('value', [$this->secretId])
        ->assertDontSee('Secret plans');

    Livewire::test(PageTableInput::class, ['value' => [$this->secretId], 'pageId' => $this->alphaId])->assertSee('Secret plans');
});

it('locks the page table\'s settings', function (string $property, mixed $value): void {
    Livewire::test(PageTableInput::class, ['value' => [], 'pageId' => $this->alphaId])->set($property, $value);
})->with([['columns', ['body']], ['templates', ['courses']], ['parent', '/'], ['trashOnRemove', true], ['disabled', false], ['pageId', 1]])
    ->throws(CannotUpdateLockedPropertyException::class);

it('keeps the page picker to pages the editor may view, or that the field held', function (): void {
    Livewire::test(PagePicker::class, ['value' => []])
        ->set('value', [$this->secretId])
        ->assertDontSee('Secret plans')
        ->call('add', $this->secretId);

    Livewire::test(PagePicker::class, ['value' => []])->call('add', $this->secretId)->assertSet('value', []);
    Livewire::test(PagePicker::class, ['value' => [$this->secretId]])->assertSee('Secret plans');
});

it('links only to pages the editor may view', function (): void {
    Livewire::test(LinkDialog::class)
        ->call('open', requestId: 'r1', pageId: $this->alphaId, language: 'default')
        ->call('choosePage', $this->secretId)
        ->assertSet('chosenPageId', null)
        ->assertSet('href', '')
        ->call('choosePage', $this->alphaId)
        ->assertSet('chosenPageId', $this->alphaId);
});

it('locks the page the link dialog chose', function (): void {
    Livewire::test(LinkDialog::class)->set('chosenPageId', $this->secretId);
})->throws(CannotUpdateLockedPropertyException::class);
