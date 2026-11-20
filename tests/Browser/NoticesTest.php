<?php

declare(strict_types=1);

/*
 * Notices wait while the pointer or the focus is on them (accessibility review, WCAG 2.2.1), so
 * people who read slowly or use a magnifier get to the end; their time runs again on leaving.
 */

const NOTICE = "window.dispatchEvent(new CustomEvent('lynx-notify', { detail: { message: 'Saved for the test.', type: 'success' } }))";

beforeEach(function (): void {
    signInSite();
    $this->actingAs(browserUser('ana@example.com'));
});

it('keeps a notice while the pointer is on it, and closes it after the pointer leaves', function (): void {
    $page = visit('/lynx/pages');
    $page->script(NOTICE);

    $page->assertSee('Saved for the test.')
        ->hover('.lynx-notice')
        ->wait(5)
        ->assertSee('Saved for the test.')
        ->hover('#lynx-main')
        ->wait(5)
        ->assertDontSee('Saved for the test.');
});

it('keeps a notice while the keyboard focus is on it', function (): void {
    $page = visit('/lynx/pages');
    $page->script(NOTICE);
    $page->script("document.querySelector('.lynx-notice').focus()");

    $page->wait(5)->assertSee('Saved for the test.');
});

it('closes a notice on its own when nothing is on it', function (): void {
    $page = visit('/lynx/pages');
    $page->script(NOTICE);

    $page->wait(5)->assertDontSee('Saved for the test.');
});
