<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Schema\BuildDraft;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Schema\Draft\DraftPaths;

/*
 * A draft's pages section (ADR-0116) creates pages after the schema: unpublished, with their
 * titles and the values the draft gives, everything else empty. A path the site has is left as it
 * is, and every mistake is reported before anything changes.
 */

beforeEach(function (): void {
    courseSite();
    $this->dir = testingPath('draft-pages');
    File::deleteDirectory($this->dir);
    File::ensureDirectoryExists($this->dir.'/views');
    config(['view.paths' => [$this->dir.'/views']]);
    app()->instance(DraftPaths::class, new DraftPaths($this->dir.'/schema', $this->dir.'/types.php', $this->dir.'/ide.php'));
});

afterEach(fn (): bool => File::deleteDirectory($this->dir));

function pagesDraft(string $pages): string
{
    $yaml = <<<YAML
    templates:
      workshops:
        children: [workshop]
        fields: [title*]
      workshop:
        parents: [workshops]
        fields: [title*, price*, summary]
    pages:
    {$pages}
    YAML;
    File::put(test()->dir.'/draft.yaml', $yaml);

    return test()->dir.'/draft.yaml';
}

it('creates the pages of a draft, unpublished, with their titles and values', function (): void {
    $draft = pagesDraft("  /workshops: Workshops\n  /workshops/clay: { template: workshop, title: Clay, price: 40 }\n");

    $this->artisan('lynx:draft', ['path' => $draft])
        ->expectsOutputToContain('create page /workshops (workshops)')
        ->expectsOutputToContain('create page /workshops/clay (workshop)')
        ->assertSuccessful();

    $clay = lynx()->pages->get('/workshops/clay/');
    expect($clay?->getUnformatted('title'))->toBe('Clay')
        ->and($clay?->price)->toBe(40)
        ->and($clay?->summary)->toBeNull()
        ->and(($clay->status ?? 0) & PageStatus::UNPUBLISHED)->not->toBe(0)
        ->and(lynx()->pages->get('/workshops/')?->template()->name)->toBe('workshops');
});

it('takes the template from the path\'s last segment and the title from the path', function (): void {
    $this->artisan('lynx:draft', ['path' => pagesDraft("  /workshops:\n")])->assertSuccessful();

    expect(lynx()->pages->get('/workshops/')?->getUnformatted('title'))->toBe('Workshops');
});

it('leaves a page the site has as it is, and builds the same draft again without changes', function (): void {
    $draft = pagesDraft("  /workshops: Workshops\n  /courses: Renamed\n");
    $this->artisan('lynx:draft', ['path' => $draft])
        ->expectsOutputToContain('keep page /courses')
        ->assertSuccessful();

    expect(lynx()->pages->get('/courses/')?->getUnformatted('title'))->not->toBe('Renamed');

    $this->artisan('lynx:draft', ['path' => $draft])
        ->expectsOutputToContain('The site already matches the draft.')
        ->assertSuccessful();
});

it('creates no page on a dry run', function (): void {
    $this->artisan('lynx:draft', ['path' => pagesDraft("  /workshops: Workshops\n"), '--dry-run' => true])
        ->expectsOutputToContain('create page /workshops (workshops)')
        ->assertSuccessful();

    expect(lynx()->pages->get('/workshops/'))->toBeNull()
        ->and(lynx()->templates->find('workshops'))->toBeNull();
});

it('refuses a draft before changing anything', function (string $pages, string $error): void {
    $this->artisan('lynx:draft', ['path' => pagesDraft($pages)])
        ->expectsOutputToContain($error)
        ->assertFailed();

    expect(lynx()->templates->find('workshops'))->toBeNull();
})->with([
    'a required field without a value' => ["  /workshops: Workshops\n  /workshops/clay: { template: workshop }\n", 'Page [/workshops/clay]: Price is required.'],
    'an unknown template' => ["  /about: { template: pag }\n", 'Page [/about]: there is no template [pag].'],
    'no template named like the path' => ["  /about: About\n", 'Page [/about]: there is no template [about]. Give one: {template: name}.'],
    'a parent that does not exist' => ["  /missing/clay: { template: workshop, price: 1 }\n", 'Page [/missing/clay]: its parent [/missing] does not exist. List it before.'],
    'a field the template lacks' => ["  /workshops: { title: Workshops, price: 3 }\n", 'Page [/workshops]: template [workshops] has no field [price].'],
    'a place the family rules refuse' => ["  /courses/clay: { template: workshop, price: 1 }\n", 'Page [/courses/clay]: Pages using [workshop] may only be placed under: workshops.'],
    'a name that is not a URL name' => ["  /Big Room: { template: workshops }\n", 'Page [/Big Room]: [Big Room] is not a URL name. Use lowercase letters, digits and hyphens.'],
]);

it('is refused by the schema.draft action, which builds schema only', function (): void {
    lynx()->run(BuildDraft::class, ['draft' => "pages:\n  /courses: Courses\n"]);
})->throws(ValidationException::class, 'pages.save');
