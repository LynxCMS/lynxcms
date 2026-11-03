<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/*
 * lynx:import and lynx:schema:apply write to a live site, so their failures write nothing, and
 * the runs that delete ask first in a terminal (SHELL_INTERACTIVE stands for one here).
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    $this->dir = testingPath('commands-'.uniqid());
});

afterEach(function (): void {
    putenv('SHELL_INTERACTIVE');
    File::deleteDirectory($this->dir);
});

it('imports nothing from a package it cannot read', function (): void {
    File::ensureDirectoryExists($this->dir);
    File::put($this->dir.'/pages.jsonl', "not json\n");

    $this->artisan('lynx:import', ['path' => $this->dir])->assertFailed();

    expect(lynx()->pages->count('template=course'))->toBe(2);
});

it('asks before --prune trashes pages, and imports nothing when refused', function (): void {
    $this->artisan('lynx:export', ['path' => $this->dir])->assertSuccessful();
    createCourses('gamma');
    putenv('SHELL_INTERACTIVE=1');

    $this->artisan('lynx:import', ['path' => $this->dir, '--prune' => true])
        ->expectsConfirmation('Import and move 1 page(s) to the trash?', 'no')
        ->assertFailed();
    expect(lynx()->pages->get('/courses/gamma/'))->not->toBeNull();

    $this->artisan('lynx:import', ['path' => $this->dir, '--prune' => true])
        ->expectsConfirmation('Import and move 1 page(s) to the trash?', 'yes')
        ->assertSuccessful();
    expect(lynx()->pages->count('template=course'))->toBe(2);
});

it('reports the pages it skips', function (): void {
    $this->artisan('lynx:export', ['path' => $this->dir])->assertSuccessful();
    lynx()->templates->add('lesson', fields: [['name' => 'title', 'required' => true]]);
    lynx()->pages->switchTemplate('/courses/alpha/', 'lesson');

    $this->artisan('lynx:import', ['path' => $this->dir])->expectsOutputToContain('Skipped')->assertSuccessful();

    expect(lynx()->pages->get('/courses/alpha/')?->template()->name)->toBe('lesson');
});

it('asks which package to import when none is given', function (): void {
    $this->artisan('lynx:export', ['path' => $this->dir])->assertSuccessful();
    putenv('SHELL_INTERACTIVE=1');

    $this->artisan('lynx:import')->expectsQuestion('Import which package?', $this->dir)->assertSuccessful();
});

it('applies no schema when a file is wrong', function (): void {
    $this->artisan('lynx:schema:export', ['--path' => $this->dir])->assertSuccessful();
    File::put($this->dir.'/fields/subtitle.yaml', Yaml::dump(['name' => 'subtitle', 'type' => 'no-such-type', 'label' => 'Subtitle']));
    File::put($this->dir.'/fields/summary.yaml', Yaml::dump(['name' => 'summary', 'type' => 'textarea', 'label' => 'Short text', 'translatable' => true]));

    $this->artisan('lynx:schema:apply', ['--path' => $this->dir])->assertFailed();

    expect(lynx()->fields->find('subtitle'))->toBeNull()
        ->and(lynx()->fields->find('summary')?->label)->not->toBe('Short text');
});

it('lists what it would delete, and deletes only with --force after asking', function (): void {
    $this->artisan('lynx:schema:export', ['--path' => $this->dir])->assertSuccessful();
    lynx()->fields->add('scratch', 'text');

    $this->artisan('lynx:schema:apply', ['--path' => $this->dir])->expectsOutputToContain('skipped')->assertSuccessful();
    expect(lynx()->fields->find('scratch'))->not->toBeNull();

    putenv('SHELL_INTERACTIVE=1');
    $this->artisan('lynx:schema:apply', ['--path' => $this->dir, '--force' => true])
        ->expectsConfirmation('Apply these changes?', 'no')
        ->assertFailed();
    expect(lynx()->fields->find('scratch'))->not->toBeNull();

    $this->artisan('lynx:schema:apply', ['--path' => $this->dir, '--force' => true])
        ->expectsConfirmation('Apply these changes?', 'yes')
        ->assertSuccessful();
    expect(lynx()->fields->find('scratch'))->toBeNull();
});
