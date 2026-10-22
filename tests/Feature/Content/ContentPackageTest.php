<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    ['alpha' => $alpha, 'beta' => $beta] = createCourses('alpha', 'beta');
    $alpha->setLanguageName('pt', 'alfa', active: true);
    $alpha->setLanguageValue('pt', 'title', 'Alfa');
    $alpha->price = 100;
    $alpha->related = [$beta->id];
    $alpha->save();
    $this->dir = sys_get_temp_dir().'/lynx-content-'.uniqid();
});

afterEach(fn () => File::deleteDirectory($this->dir));

/** @return list<array<string, mixed>> */
function packagedPages(string $dir): array
{
    return array_map(fn (string $line): array => json_decode($line, true), array_values(array_filter(explode("\n", File::get($dir.'/pages.jsonl')))));
}

function contentPage(string $path): ?Page
{
    return lynx()->pages->get($path);
}

it('exports the schema and every page, parents first, keyed by their names', function (): void {
    lynx()->pages->trash('/courses/beta/');
    lynx()->pages->saveDraft('/courses/alpha/', ['price' => 1]);

    $count = app(ContentExporter::class)->export($this->dir);
    $pages = packagedPages($this->dir);
    $alpha = collect($pages)->firstWhere('key', '/courses/alpha/');

    expect($count)->toBe(3)
        ->and(array_column($pages, 'key'))->toBe(['/', '/courses/', '/courses/alpha/'])
        ->and(json_decode(File::get($this->dir.'/manifest.json'), true))->toMatchArray(['format' => 'lynx-content', 'version' => 1, 'pages' => 3])
        ->and(File::exists($this->dir.'/schema/templates/course.yaml'))->toBeTrue()
        ->and($alpha)->toMatchArray(['parent' => '/courses/', 'template' => 'course'])
        ->and($alpha['names']['pt'])->toBe(['name' => 'alfa', 'active' => true])
        ->and($alpha['values']['pt']['title'])->toBe('Alfa')
        ->and($alpha['values']['*']['price'])->toBe(100);
});

it('writes page references as keys, so they survive on another site', function (): void {
    app(ContentExporter::class)->export($this->dir);

    expect(collect(packagedPages($this->dir))->firstWhere('key', '/courses/alpha/')['values']['*']['related'])->toBe(['/courses/beta/']);
});

it('recreates missing pages on import, with names, values and references', function (): void {
    app(ContentExporter::class)->export($this->dir);
    lynx()->pages->delete('/courses/');

    $this->artisan('lynx:import', ['path' => $this->dir])->expectsOutputToContain('3 created')->assertSuccessful();

    expect((string) contentPage('/courses/alpha/')?->getLanguageValue('pt', 'title'))->toBe('Alfa')
        ->and(contentPage('/courses/alpha/')?->get('price'))->toBe(100)
        ->and(contentPage('/courses/alpha/')?->localUrl('pt'))->toBe('/pt/cursos/alfa')
        ->and(contentPage('/courses/alpha/')?->get('related')->ids())->toBe([(int) contentPage('/courses/beta/')?->id]);
});

it('updates existing pages, and changes nothing the second time', function (): void {
    app(ContentExporter::class)->export($this->dir);
    $alpha = contentPage('/courses/alpha/');
    $alpha->price = 5;
    $alpha->save();

    $this->artisan('lynx:import', ['path' => $this->dir])->expectsOutputToContain('1 updated')->assertSuccessful();
    expect(contentPage('/courses/alpha/')?->get('price'))->toBe(100);

    $this->artisan('lynx:import', ['path' => $this->dir])->expectsOutputToContain('0 created, 0 updated')->assertSuccessful();
});

it('moves pages missing from the package to the trash with --prune', function (): void {
    app(ContentExporter::class)->export($this->dir);
    createCourses('gamma');

    $this->artisan('lynx:import', ['path' => $this->dir])->assertSuccessful();
    expect(contentPage('/courses/gamma/'))->not->toBeNull();

    $this->artisan('lynx:import', ['path' => $this->dir, '--prune' => true])->expectsOutputToContain('1 trashed')->assertSuccessful();
    expect(lynx()->pages->find('template=course')->count())->toBe(2);
});

it('previews an import without writing', function (): void {
    app(ContentExporter::class)->export($this->dir);
    lynx()->pages->delete('/courses/');

    $this->artisan('lynx:import', ['path' => $this->dir, '--dry-run' => true])->expectsOutputToContain('Dry run')->assertSuccessful();

    expect(contentPage('/courses/'))->toBeNull();
});

it('refuses a directory that is not a content package', function (): void {
    File::ensureDirectoryExists($this->dir);

    $this->artisan('lynx:import', ['path' => $this->dir])->expectsOutputToContain('not a Lynx content package')->assertFailed();
});

it('backs up to a zip, keeps the newest ones, and restores from it', function (): void {
    config(['lynx.backups.path' => $this->dir, 'lynx.backups.keep' => 2]);

    foreach (range(1, 3) as $n) {
        $this->travel(1)->minutes();
        $this->artisan('lynx:backup')->assertSuccessful();
    }

    $zips = File::glob($this->dir.'/lynx-*.zip');
    expect($zips)->toHaveCount(2);

    lynx()->pages->delete('/courses/');
    $this->artisan('lynx:import', ['path' => end($zips)])->expectsOutputToContain('3 created')->assertSuccessful();
    expect(contentPage('/courses/alpha/'))->not->toBeNull();
});

it('schedules backups when configured', function (): void {
    expect(collect(app(Schedule::class)->events())->contains(fn ($event): bool => str_contains((string) $event->command, 'lynx:backup')))->toBeFalse();
});
