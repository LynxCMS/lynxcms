<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

/*
 * "Your First Site" (docs/getting-started/first-site.md) shows the files in
 * docs/snippets/first-site. This test runs those same files, so the tutorial
 * fails here before it fails a reader.
 */

const TUTORIAL = __DIR__.'/../../../docs/snippets/first-site';

beforeEach(function (): void {
    installLynx();
    // The workbench has views of its own; the tutorial's come first, and found ones are forgotten.
    View::prependLocation(TUTORIAL.'/views');
    View::getFinder()->flush();
    require TUTORIAL.'/schema.php';
    require TUTORIAL.'/pages.php';
});

it('serves the home page with the latest posts', function (): void {
    $this->get('/')->assertOk()
        ->assertSeeInOrder(['Latest posts', 'A second post', 'Hello, world'])
        ->assertDontSee('aria-label="Languages"', false);
});

it('lists the posts on the blog page, newest first', function (): void {
    $this->get('/blog')->assertOk()
        ->assertSeeInOrder(['Notes from the team.', 'A second post', 'Hello, world']);
});

it('shows a post with its date and body', function (): void {
    $this->get('/blog/hello-world')->assertOk()
        ->assertSee('<time datetime="2026-10-01">Oct 1, 2026</time>', false)
        ->assertSee('Welcome to our new <strong>blog</strong>.', false);
});

it('serves the translated post, with fallback for what is not translated', function (): void {
    require TUTORIAL.'/languages.php';

    $this->get('/pt/blogue/ola-mundo')->assertOk()
        ->assertSee('Olá, mundo')
        ->assertSee('aria-label="Languages"', false)
        // The body is not translated: it shows in English, marked as such.
        ->assertSee('lang="en"', false);
});

it('links untranslated posts to their English address', function (): void {
    require TUTORIAL.'/languages.php';

    $this->get('/pt/blogue')->assertOk()
        ->assertSee('href="/pt/blogue/ola-mundo"', false)
        ->assertSee('href="/blog/second-post"', false);
});
