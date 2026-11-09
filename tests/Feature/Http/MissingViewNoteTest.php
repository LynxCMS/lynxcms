<?php

declare(strict_types=1);

/*
 * A template without a view renders with Lynx's plain lynx::page. In debug mode that page says
 * which view to create and how to start it, so a developer isn't left wondering; visitors of a
 * live site (debug off) never see the note.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->templates->add('workshop', fields: ['title']);
    $page = lynx()->pages->new('workshop', '/');
    $page->name = 'clay';
    $page->title = 'Clay';
    $page->save();
});

it('names the view to create in debug mode', function (): void {
    config(['app.debug' => true]);

    $this->get('/clay')->assertOk()
        ->assertSee('data-lynx-missing-view', escape: false)
        ->assertSee('templates/workshop.blade.php')
        ->assertSee('lynx:make:view workshop');
});

it('shows nothing of it to visitors of a live site', function (): void {
    config(['app.debug' => false]);

    $this->get('/clay')->assertOk()->assertDontSee('data-lynx-missing-view', escape: false);
});
