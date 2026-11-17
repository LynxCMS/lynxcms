<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use LynxCms\Admin\Home\HomeScreen;

/*
 * The site's developer arranges the home screen in code: every entry has a key, Lynx's own lists
 * are panels like a module's, and remove() and move() change entries whoever added them, before
 * or after. A panel goes in the main column or the side column.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function homeTestPanel(string $name): string
{
    $views = testingPath('home-layout-views');
    File::ensureDirectoryExists($views);
    File::put($views.'/'.$name.'.blade.php', '<section data-home-panel="'.$name.'"></section>');
    View::addNamespace('home-layout', $views);

    return 'home-layout::'.$name;
}

it('has Lynx\'s own lists as keyed panels, the drafts and schedule in the main column', function (): void {
    $home = app(HomeScreen::class);

    expect($home->panelKeys())->toBe(['drafts', 'upcoming', 'forms'])
        ->and($home->panelKeys(HomeScreen::SIDE))->toBe(['recent']);
});

it('removes an entry of any kind by its key', function (): void {
    app(HomeScreen::class)->remove('site-texts');
    app(HomeScreen::class)->remove('unpublished');
    app(HomeScreen::class)->remove('drafts');

    $this->get('/lynx')->assertOk()
        ->assertDontSee('data-home-action="site-texts"', false)
        ->assertSee('data-home-action="new-page"', false)
        ->assertDontSee('data-home-panel="drafts"', false)
        ->assertSee('data-home-panel="upcoming"', false);
    expect(array_column(app(HomeScreen::class)->stats(), 'key'))->not->toContain('unpublished');
});

it('removes an entry a module adds after the call', function (): void {
    $home = app(HomeScreen::class);
    $home->remove('reports');
    $home->panel(homeTestPanel('reports'), key: 'reports');

    $this->get('/lynx')->assertOk()->assertDontSee('data-home-panel="reports"', false);
});

it('moves an entry, whoever added it', function (): void {
    $home = app(HomeScreen::class);
    $home->move('drafts', order: 90);
    $home->move('site-texts', order: 1);

    expect($home->panelKeys())->toBe(['upcoming', 'forms', 'drafts'])
        ->and(array_slice(array_column($home->actions(), 'key'), 0, 2))->toBe(['site-texts', 'new-page']);
});

it('moves a panel to the other column', function (): void {
    $home = app(HomeScreen::class);
    $home->move('upcoming', order: 5, column: HomeScreen::SIDE);

    expect($home->panelKeys())->toBe(['drafts', 'forms'])
        ->and($home->panelKeys(HomeScreen::SIDE))->toBe(['upcoming', 'recent']);
});

it('keys a panel by its view unless given a key', function (): void {
    $home = app(HomeScreen::class);
    $home->panel('reports::home', order: 40);
    $home->panel('weather::home', order: 41, key: 'weather');

    expect($home->panelKeys())->toBe(['drafts', 'upcoming', 'forms', 'reports::home', 'weather']);
});

it('puts a panel in the side column, below the recent changes by default', function (): void {
    app(HomeScreen::class)->panel(homeTestPanel('weather'), column: HomeScreen::SIDE);

    $this->get('/lynx')->assertOk()
        ->assertSeeInOrder(['data-home-panel="recent"', 'data-home-panel="weather"'], false);
});

it('spans the main column across the page when the side column is empty', function (): void {
    app(HomeScreen::class)->remove('recent');

    $this->get('/lynx')->assertOk()
        ->assertDontSee('data-home-panel="recent"', false)
        ->assertDontSee('data-home-side', false);
});

it('ignores a key nothing has', function (): void {
    app(HomeScreen::class)->remove('gone');
    app(HomeScreen::class)->move('gone', order: 1);

    $this->get('/lynx')->assertOk()->assertSee('data-home-panel="drafts"', false);
});

it('refuses a column it does not have', function (): void {
    app(HomeScreen::class)->panel('reports::home', column: 'footer');
})->throws(InvalidArgumentException::class, 'footer');
