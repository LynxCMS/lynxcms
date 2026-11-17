<?php

declare(strict_types=1);

/*
 * A site about lynxes needs /lynx for its own page, so its developer moves the admin to /manage
 * (LYNX_ADMIN_PREFIX=manage). Every admin screen, the sign-in and Fortify's redirects move with it,
 * and /lynx belongs to the site.
 */

beforeEach(function (): void {
    installLynx();
    lynx()->templates->add('animal', fields: [['name' => 'title', 'required' => true]]);
});

it('serves the admin and its sign-in at the chosen path', function (): void {
    $this->get('/manage/pages')->assertRedirect('/manage/login');
    $this->get('/manage/login')->assertOk();
    $this->get('/lynx/login')->assertNotFound();
});

it('sends people to the chosen path after signing in and out', function (): void {
    expect(config('fortify.home'))->toBe('/manage/pages')
        ->and(config('fortify.redirects.logout'))->toBe('/manage/login');
});

it('leaves /lynx to the site', function (): void {
    $page = lynx()->pages->new('animal', '/');
    $page->name = 'lynx';
    $page->title = 'The Iberian lynx';
    $page->save();

    $this->get('/lynx')->assertOk()->assertSee('The Iberian lynx');
});
