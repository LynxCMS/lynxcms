<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Core\Schema\Draft\DraftPaths;

/*
 * lynx:draft builds a draft (ADR-0116): its fields and templates through schema sync, in one
 * transaction, then a starting view for each new template with a URL, the site's schema files and
 * its PHPStan and IDE types when the site keeps them.
 */

beforeEach(function (): void {
    courseSite();
    $this->dir = testingPath('draft');
    File::deleteDirectory($this->dir);
    File::ensureDirectoryExists($this->dir.'/views');
    config(['view.paths' => [$this->dir.'/views']]);
    app('view')->getFinder()->setPaths([$this->dir.'/views']);
    app()->instance(DraftPaths::class, new DraftPaths($this->dir.'/schema', $this->dir.'/types.php', $this->dir.'/ide.php'));
});

afterEach(function (): void {
    File::deleteDirectory($this->dir);
    putenv('SHELL_INTERACTIVE');
});

function draftFile(string $yaml): string
{
    File::put(test()->dir.'/draft.yaml', $yaml);

    return test()->dir.'/draft.yaml';
}

const WORKSHOP_DRAFT = <<<'YAML'
fields:
  seats: integer min:1
templates:
  workshop:
    url: true
    parents: [courses]
    fields: [title*, summary, seats]
  workshop-settings:
    url: false
    singleton: true
    fields: [title*]
YAML;

const COURSE_SITE_DRAFT = <<<'YAML'
fields:
  summary: textarea translatable
  body: textarea:html translatable
  price: integer
  related: page
templates:
  courses:
    fields: [title*]
  course:
    fields: [title*, summary, body, price, related]
YAML;

it('builds the fields and templates of a draft', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT)])->assertSuccessful();

    expect(lynx()->fields->find('seats')?->type)->toBe('integer')
        ->and(lynx()->templates->fields('workshop')->keys()->all())->toBe(['title', 'summary', 'seats'])
        ->and(lynx()->templates->find('workshop')?->config()['parent_templates'])->toBe(['courses']);
});

it('changes nothing when the draft describes the site as it is', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile(COURSE_SITE_DRAFT)])
        ->expectsOutputToContain('The site already matches the draft.')
        ->assertSuccessful();
});

it('changes nothing the second time a draft is built', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT)])->assertSuccessful();

    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT)])
        ->expectsOutputToContain('The site already matches the draft.')
        ->assertSuccessful();
});

it('previews the changes on a dry run, and changes nothing', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT), '--dry-run' => true])
        ->expectsOutputToContain('create field seats')
        ->expectsOutputToContain('create template workshop')
        ->expectsOutputToContain('write view templates/workshop.blade.php')
        ->expectsOutputToContain('Dry run: nothing was changed.')
        ->assertSuccessful();

    expect(lynx()->fields->find('seats'))->toBeNull()
        ->and(File::exists($this->dir.'/views/templates/workshop.blade.php'))->toBeFalse();
});

it('lists a changed template as an update', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile("templates:\n  course:\n    label: Training\n    fields: [title*, summary, body, price, related]\n"), '--dry-run' => true])
        ->expectsOutputToContain('update template course')
        ->assertSuccessful();
});

it('refuses a draft that leaves fields out of a template the site has, naming them', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile("templates:\n  course:\n    fields: [title*, summary]\n")])
        ->expectsOutputToContain('Template [course]: the draft leaves out body, price, related')
        ->assertFailed();

    expect(lynx()->templates->fields('course')->keys()->all())->toBe(['title', 'summary', 'body', 'price', 'related']);
});

it('removes the fields a draft leaves out of a template with --prune-fields', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile("templates:\n  course:\n    fields: [title*, summary]\n"), '--prune-fields' => true])
        ->expectsOutputToContain('remove field body from template course')
        ->assertSuccessful();

    expect(lynx()->templates->fields('course')->keys()->all())->toBe(['title', 'summary'])
        ->and(lynx()->fields->find('body'))->not->toBeNull();
});

it('writes a view for a new template with a URL only', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT)])->assertSuccessful();

    expect(File::get($this->dir.'/views/templates/workshop.blade.php'))->toContain('seats')
        ->and(File::exists($this->dir.'/views/templates/workshop-settings.blade.php'))->toBeFalse();
});

it('keeps a view that exists for a new template', function (): void {
    File::ensureDirectoryExists($this->dir.'/views/templates');
    File::put($this->dir.'/views/templates/workshop.blade.php', 'mine');

    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT)])->expectsOutputToContain('keep view')->assertSuccessful();

    expect(File::get($this->dir.'/views/templates/workshop.blade.php'))->toBe('mine');
});

it('replaces the view of a new template with --force-views', function (): void {
    File::ensureDirectoryExists($this->dir.'/views/templates');
    File::put($this->dir.'/views/templates/workshop.blade.php', 'mine');

    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT), '--force-views' => true])->assertSuccessful();

    expect(File::get($this->dir.'/views/templates/workshop.blade.php'))->not->toBe('mine');
});

it('writes a view for each block type of a new template\'s matrix, keeping one that exists', function (): void {
    File::ensureDirectoryExists($this->dir.'/views/templates/fields/blocks');
    File::put($this->dir.'/views/templates/fields/blocks/note.blade.php', 'My note');
    $draft = draftFile("fields:\n  caption: text\n  blocks:\n    type: matrix\n    types:\n      - slug: quote\n        fields: [caption*]\n      - slug: note\n        fields: [caption]\ntemplates:\n  workshop:\n    fields: [title*, blocks]\n");

    $this->artisan('lynx:draft', ['path' => $draft, '--dry-run' => true])
        ->expectsOutputToContain('write view templates/fields/blocks/quote.blade.php')
        ->expectsOutputToContain('keep view templates/fields/blocks/note.blade.php')
        ->assertSuccessful();

    $this->artisan('lynx:draft', ['path' => $draft])->assertSuccessful();

    expect(File::get($this->dir.'/views/templates/fields/blocks/quote.blade.php'))->toContain('@field($block->caption)')
        ->and(File::get($this->dir.'/views/templates/fields/blocks/note.blade.php'))->toBe('My note');
});

it('writes no views with --no-views', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT), '--no-views' => true])->assertSuccessful();

    expect(File::exists($this->dir.'/views/templates/workshop.blade.php'))->toBeFalse();
});

it('writes the draft\'s items into the schema folder when the site keeps one', function (): void {
    File::ensureDirectoryExists($this->dir.'/schema');

    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT)])->assertSuccessful();

    expect(File::get($this->dir.'/schema/fields/seats.yaml'))->toContain('uuid: '.lynx()->fields->find('seats')?->uuid)
        ->and(File::exists($this->dir.'/schema/templates/workshop.yaml'))->toBeTrue()
        ->and(File::exists($this->dir.'/schema/templates/course.yaml'))->toBeFalse();
});

it('starts no schema folder of its own', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT)])
        ->expectsOutputToContain('lynx:schema:export')
        ->assertSuccessful();

    expect(File::isDirectory($this->dir.'/schema'))->toBeFalse();
});

it('refreshes the PHPStan and IDE types the site keeps, and only those', function (): void {
    File::put($this->dir.'/types.php', '<?php return [];');

    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT)])->assertSuccessful();

    expect(File::get($this->dir.'/types.php'))->toContain('seats')
        ->and(File::exists($this->dir.'/ide.php'))->toBeFalse();
});

it('builds nothing when the draft has an error', function (): void {
    $this->artisan('lynx:draft', ['path' => draftFile("fields:\n  seats: integr\n")])
        ->expectsOutputToContain('Did you mean [integer]?')
        ->assertFailed();

    expect(lynx()->fields->find('seats'))->toBeNull();
});

it('fails clearly without a draft file', function (): void {
    $this->artisan('lynx:draft', ['path' => $this->dir.'/missing.yaml'])
        ->expectsOutputToContain('lynx:draft --stub')
        ->assertFailed();
});

it('writes a starting draft with --stub', function (): void {
    $this->artisan('lynx:draft', ['path' => $this->dir.'/new.yaml', '--stub' => true])->assertSuccessful();

    $this->artisan('lynx:draft', ['path' => $this->dir.'/new.yaml', '--dry-run' => true])->assertSuccessful();
});

it('writes the site as a draft with --from-site, and refuses to overwrite a draft', function (): void {
    $this->artisan('lynx:draft', ['path' => $this->dir.'/site.yaml', '--from-site' => true])->assertSuccessful();

    expect(File::get($this->dir.'/site.yaml'))->toContain('courses:');

    $this->artisan('lynx:draft', ['path' => $this->dir.'/site.yaml', '--from-site' => true])
        ->expectsOutputToContain('A draft exists')
        ->assertFailed();
    $this->artisan('lynx:draft', ['path' => $this->dir.'/site.yaml'])
        ->expectsOutputToContain('The site already matches the draft.')
        ->assertSuccessful();
});

it('asks before building in a terminal', function (): void {
    putenv('SHELL_INTERACTIVE=1');

    $this->artisan('lynx:draft', ['path' => draftFile(WORKSHOP_DRAFT)])
        ->expectsConfirmation('Build this draft?', 'no')
        ->assertFailed();

    expect(lynx()->fields->find('seats'))->toBeNull();
});
