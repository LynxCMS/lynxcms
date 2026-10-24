<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * Working on a Live Site: refreshing content from production with --with-schema puts
 * production's schema back, and applying the schema files exported beforehand restores the
 * developer's changes, as the page's refresh order says.
 */

beforeEach(function (): void {
    $this->folder = testingPath('live-site');
    File::deleteDirectory($this->folder);
    File::ensureDirectoryExists($this->folder);

    // "Production": a site, exported as a content package.
    $this->artisan('lynx:import', ['path' => __DIR__.'/../../Fixtures/content/course-site', '--with-schema' => true])->assertSuccessful();
    $this->artisan('lynx:export', ['path' => $this->folder.'/site.zip'])->assertSuccessful();

    // Local work on the schema: a new field on a template, and a changed label.
    lynx()->fields->add('subtitle', 'text', label: 'Subtitle');
    lynx()->templates->update('course', ['add' => ['subtitle']]);
    lynx()->fields->update('title', ['label' => 'Heading']);
});

afterEach(fn () => File::deleteDirectory($this->folder));

it('puts production\'s schema back on import, keeping fields and values', function (): void {
    $this->artisan('lynx:import', ['path' => $this->folder.'/site.zip', '--with-schema' => true])->assertSuccessful();

    expect(lynx()->templates->fields('course')->has('subtitle'))->toBeFalse()
        ->and(lynx()->fields->find('subtitle'))->not->toBeNull()
        ->and((string) lynx()->fields->find('title')?->label)->toBe('Title');
});

it('keeps local schema changes when they are exported first and applied after', function (): void {
    $this->artisan('lynx:schema:export', ['--path' => $this->folder.'/schema'])->assertSuccessful();
    $this->artisan('lynx:import', ['path' => $this->folder.'/site.zip', '--with-schema' => true])->assertSuccessful();
    $this->artisan('lynx:schema:apply', ['--path' => $this->folder.'/schema'])->assertSuccessful();

    expect(lynx()->templates->fields('course')->has('subtitle'))->toBeTrue()
        ->and((string) lynx()->fields->find('title')?->label)->toBe('Heading')
        ->and((string) lynx()->pages->get('/courses/alpha/')?->title)->toBe('Alpha');
});
