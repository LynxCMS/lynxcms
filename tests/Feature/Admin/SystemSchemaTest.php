<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Editor\TemplateChoices;
use LynxCms\Admin\Livewire\FieldList;
use LynxCms\Admin\Livewire\TemplateList;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->menus()->create('top', 'Top');
    lynx()->pages->trash('/courses/alpha/');
    test()->actingAs(new GenericUser(['id' => 1]));
});

it('hides system templates unless asked, and marks them', function (): void {
    Livewire::test(TemplateList::class)
        ->assertSee('course')
        ->assertDontSeeHtml('templates/menu-item/edit')
        ->assertDontSeeHtml('templates/trash/edit')
        ->assertSee('Show system templates')
        ->set('showSystem', true)
        ->assertSeeHtml('templates/menu-item/edit')
        ->assertSeeHtml('templates/trash/edit')
        ->assertSee('system · Menus');
});

it('hides system fields unless asked', function (): void {
    Livewire::test(FieldList::class)
        ->assertSee('price')
        ->assertDontSeeHtml('fields/menu_url/edit')
        ->set('showSystem', true)
        ->assertSeeHtml('fields/menu_url/edit');
});

it('never offers system templates for new pages', function (): void {
    $choices = array_keys(app(TemplateChoices::class)->for(lynx()->pages->get('/')));

    expect($choices)->not->toContain('menus', 'menu', 'menu-item', 'trash');
});
