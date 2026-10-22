<?php

declare(strict_types=1);

// No courseSite(): a fresh database, as on a new environment seeded from a content package.
it('imports a package into a site without pages, starting with the home page', function (): void {
    $this->artisan('lynx:import', ['path' => __DIR__.'/../../Fixtures/content/course-site', '--with-schema' => true])
        ->expectsOutputToContain('3 created')
        ->assertSuccessful();

    expect(lynx()->pages->get('/')?->template()->name)->toBe('home')
        ->and((string) lynx()->pages->get('/courses/alpha/')?->title)->toBe('Alpha');
});
