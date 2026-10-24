<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Schema\SchemaSync;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/lynx-schema-'.uniqid();
    courseSite();
    lynx()->languages->add('pt-br', 'Português (Brasil)', fallback: 'pt');
});

afterEach(fn () => File::deleteDirectory($this->dir));

function sync(): SchemaSync
{
    return app(SchemaSync::class);
}

/** Wipe the Lynx schema of this database, as on a fresh machine. */
function freshMachine(): void
{
    foreach (lynx()->fields->all() as $field) {
        Schema::drop($field->table());
    }

    foreach (['lynx_activity_events', 'lynx_value_meta', 'lynx_page_paths', 'lynx_page_languages', 'lynx_page_closure', 'lynx_template_fields'] as $table) {
        DB::table($table)->delete();
    }

    DB::table('lynx_pages')->orderByDesc('id')->get()->each(fn ($p) => DB::table('lynx_pages')->where('id', $p->id)->delete());

    foreach (['lynx_templates', 'lynx_fields', 'lynx_settings'] as $table) {
        DB::table($table)->delete();
    }

    DB::table('lynx_languages')->update(['fallback_id' => null]);
    DB::table('lynx_languages')->delete();
    app(ActionRunner::class)->flushCaches();
}

it('exports one YAML file per language, field, template and role', function (): void {
    $count = sync()->export($this->dir);

    // templates: install's not-found template too; roles: the superuser, editor and guest roles install creates
    expect($count)->toBe(3 + 5 + 4 + 3)
        ->and(File::exists("{$this->dir}/languages/pt-br.yaml"))->toBeTrue()
        ->and(Yaml::parseFile("{$this->dir}/languages/pt-br.yaml")['fallback'])->toBe('pt')
        ->and(Yaml::parseFile("{$this->dir}/templates/course.yaml")['fields'][0])->toBe(['name' => 'title', 'required' => true]);
});

it('rebuilds the same schema on another machine and is idempotent', function (): void {
    sync()->export($this->dir);
    $uuid = lynx()->fields->get('body')->uuid;

    freshMachine();
    $result = sync()->apply($this->dir);

    expect($result->errors)->toBe([])
        ->and(lynx()->languages->default()->code)->toBe('default')
        ->and(array_map(fn (Language $l) => $l->code, lynx()->languages->chain('pt-br')))->toBe(['pt-br', 'pt', 'default'])
        ->and(lynx()->fields->get('body')->uuid)->toBe($uuid)
        ->and(lynx()->templates->fields('course')->keys()->all())->toBe(['title', 'summary', 'body', 'price', 'related'])
        ->and(sync()->apply($this->dir)->isEmpty())->toBeTrue();
});

it('applies changes made in another environment', function (): void {
    sync()->export($this->dir);

    $course = Yaml::parseFile("{$this->dir}/templates/course.yaml");
    $course['label'] = 'Training course';
    $course['fields'][] = 'subtitle';
    File::put("{$this->dir}/templates/course.yaml", Yaml::dump($course, 4, 2));
    File::put("{$this->dir}/fields/subtitle.yaml", Yaml::dump([
        'uuid' => '0b9b6f8e-3f2a-4c7e-9d5b-1a2b3c4d5e6f', 'name' => 'subtitle', 'type' => 'text',
        'label' => 'Subtitle', 'translatable' => true, 'settings' => [],
    ]));

    $dry = sync()->apply($this->dir, dryRun: true);
    expect($dry->changes)->not->toBeEmpty()->and(lynx()->fields->find('subtitle'))->toBeNull();

    sync()->apply($this->dir);
    expect(lynx()->templates->get('course')->label)->toBe('Training course')
        ->and(lynx()->templates->fields('course')->has('subtitle'))->toBeTrue();
});

it('only deletes missing items with force', function (): void {
    sync()->export($this->dir);
    lynx()->fields->add('scratch', 'text');

    $result = sync()->apply($this->dir);
    expect($result->skipped)->toContain('Delete field scratch (not in the files; run with --force to delete)')
        ->and(lynx()->fields->find('scratch'))->not->toBeNull();

    sync()->apply($this->dir, force: true);
    expect(lynx()->fields->find('scratch'))->toBeNull();
});

it('runs from Artisan', function (): void {
    $this->artisan('lynx:schema:export', ['--path' => $this->dir])->assertSuccessful();
    $this->artisan('lynx:schema:apply', ['--path' => $this->dir])->expectsOutputToContain('already matches')->assertSuccessful();
});

it('creates subfields before the repeaters and matrices that use them on a fresh machine', function (): void {
    // Files are read by name: "credits" and "blocks" sort before the subfields they hold.
    lynx()->fields->add('role', 'text', translatable: true);
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'role']]]);
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [['slug' => 'text', 'label' => 'Text', 'fields' => [['name' => 'summary']]]]]);
    lynx()->templates->update('course', ['add' => ['credits', 'blocks']]);
    sync()->export($this->dir);

    freshMachine();
    $result = sync()->apply($this->dir);

    expect($result->errors)->toBe([])
        ->and(lynx()->templates->fields('course')->keys()->all())->toContain('credits', 'blocks');
});
