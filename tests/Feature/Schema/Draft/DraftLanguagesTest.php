<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Core\Actions\Schema\BuildDraft;
use LynxCms\Core\Schema\Draft\DraftPaths;
use LynxCms\Core\Schema\Draft\DraftWriter;
use Symfony\Component\Yaml\Yaml;

/*
 * A draft's languages section (ADR-0116): it adds languages and changes them, before the fields and
 * templates whose labels use them, in the same transaction. The entry marked default is the site's
 * default language, whatever its code is now, so a draft can rename it. A draft never removes a
 * language and never makes another language the default.
 */

beforeEach(function (): void {
    installLynx();
    $this->dir = testingPath('draft-languages');
    File::deleteDirectory($this->dir);
    File::ensureDirectoryExists($this->dir.'/views');
    config(['view.paths' => [$this->dir.'/views']]);
    app('view')->getFinder()->setPaths([$this->dir.'/views']);
    app()->instance(DraftPaths::class, new DraftPaths($this->dir.'/schema', $this->dir.'/types.php', $this->dir.'/ide.php'));
});

afterEach(fn (): bool => File::deleteDirectory($this->dir));

function languageDraft(string $yaml): string
{
    File::put(test()->dir.'/draft.yaml', $yaml);

    return test()->dir.'/draft.yaml';
}

const TWO_LANGUAGES = <<<'YAML'
languages:
  en: { name: English, default: true }
  pt: { name: Português, locale: pt-PT, fallback: en }
fields:
  category:
    type: choice
    label: { en: Category, pt: Categoria }
    options:
      News: { pt: Notícias }
templates:
  post:
    label: { en: Post, pt: Artigo }
    fields: [title*, category]
YAML;

it('adds languages and uses them in labels in the same build', function (): void {
    $this->artisan('lynx:draft', ['path' => languageDraft(TWO_LANGUAGES), '--no-views' => true])->assertSuccessful();

    $pt = lynx()->languages->find('pt');

    expect(lynx()->languages->default()->code)->toBe('en')
        ->and(lynx()->languages->default()->url_prefix)->toBe('')
        ->and($pt?->locale)->toBe('pt-PT')
        ->and($pt?->fallback_id)->toBe(lynx()->languages->default()->id)
        ->and(lynx()->templates->find('post')?->labels()->toStorage())->toBe(['en' => 'Post', 'pt' => 'Artigo'])
        ->and(lynx()->language('pt', fn () => lynx()->fields->choices('category')->labels()))->toContain('Notícias');
});

it('renames the default language, and the labels stored under its code follow', function (): void {
    lynx()->languages->create('pt', 'Português');
    lynx()->fields->create('summary', 'textarea', label: ['default' => 'Summary', 'pt' => 'Resumo']);

    $this->artisan('lynx:draft', ['path' => languageDraft("languages:\n  en: { name: English, default: true }\n"), '--no-views' => true])->assertSuccessful();

    expect(lynx()->languages->default()->code)->toBe('en')
        ->and(lynx()->languages->find('default'))->toBeNull()
        ->and(lynx()->fields->find('summary')?->labels()->toStorage())->toBe(['en' => 'Summary', 'pt' => 'Resumo']);
});

it('plans the languages first, and changes nothing on a dry run', function (): void {
    $this->artisan('lynx:draft', ['path' => languageDraft(TWO_LANGUAGES), '--dry-run' => true])
        ->expectsOutputToContain('update language default, code en')
        ->expectsOutputToContain('create language pt')
        ->assertSuccessful();

    expect(lynx()->languages->default()->code)->toBe('default')
        ->and(lynx()->languages->find('pt'))->toBeNull()
        ->and(lynx()->templates->find('post'))->toBeNull();
});

it('changes nothing when built again', function (): void {
    $path = languageDraft(TWO_LANGUAGES);
    $this->artisan('lynx:draft', ['path' => $path, '--no-views' => true])->assertSuccessful();

    $this->artisan('lynx:draft', ['path' => $path, '--dry-run' => true])
        ->expectsOutputToContain('The site already matches the draft.')
        ->assertSuccessful();
});

it('keeps the languages a draft leaves out', function (): void {
    lynx()->languages->create('fr', 'Français');

    $this->artisan('lynx:draft', ['path' => languageDraft(TWO_LANGUAGES), '--no-views' => true])->assertSuccessful();

    expect(lynx()->languages->find('fr'))->not->toBeNull();
});

it('keeps a language\'s settings the draft does not give', function (): void {
    lynx()->languages->create('pt', 'Português', locale: 'pt-PT');
    lynx()->languages->update('pt', ['label' => 'PT']);

    $this->artisan('lynx:draft', ['path' => languageDraft("languages:\n  pt: Portuguese\n"), '--no-views' => true])->assertSuccessful();

    expect(lynx()->languages->find('pt'))->name->toBe('Portuguese')->locale->toBe('pt-PT')->label->toBe('PT');
});

it('rolls the languages back when the rest of the draft fails', function (): void {
    lynx()->fields->create('category', 'text');

    $this->artisan('lynx:draft', ['path' => languageDraft(TWO_LANGUAGES), '--no-views' => true])->assertFailed();

    expect(lynx()->languages->default()->code)->toBe('default')
        ->and(lynx()->languages->find('pt'))->toBeNull();
});

it('refuses to make another language the default', function (): void {
    lynx()->languages->create('pt', 'Português');

    $this->artisan('lynx:draft', ['path' => languageDraft("languages:\n  pt: { name: Português, default: true }\n"), '--dry-run' => true])
        ->expectsOutputToContain('[pt] is not the default language')
        ->assertFailed();
});

it('refuses two default languages', function (): void {
    $this->artisan('lynx:draft', ['path' => languageDraft("languages:\n  en: { name: English, default: true }\n  pt: { name: Português, default: true }\n"), '--dry-run' => true])
        ->expectsOutputToContain('one default')
        ->assertFailed();
});

it('refuses a fallback to a language nobody defines, suggesting one', function (): void {
    $this->artisan('lynx:draft', ['path' => languageDraft("languages:\n  en: { name: English, default: true }\n  pt: { name: Português, fallback: eb }\n"), '--dry-run' => true])
        ->expectsOutputToContain('Did you mean [en]?')
        ->assertFailed();
});

it('refuses a fallback on the default language', function (): void {
    $this->artisan('lynx:draft', ['path' => languageDraft("languages:\n  en: { name: English, default: true, fallback: pt }\n  pt: Português\n"), '--dry-run' => true])
        ->expectsOutputToContain('no fallback')
        ->assertFailed();
});

it('refuses an unknown language setting, suggesting the one meant', function (): void {
    $this->artisan('lynx:draft', ['path' => languageDraft("languages:\n  pt: { name: Português, locle: pt-PT }\n"), '--dry-run' => true])
        ->expectsOutputToContain('Did you mean [locale]?')
        ->assertFailed();
});

it('refuses a new language without a name', function (): void {
    $this->artisan('lynx:draft', ['path' => languageDraft("languages:\n  pt: { locale: pt-PT }\n"), '--dry-run' => true])
        ->expectsOutputToContain('needs a name')
        ->assertFailed();
});

it('refuses a code that is not a language code', function (): void {
    $this->artisan('lynx:draft', ['path' => languageDraft("languages:\n  Pt BR: Português\n"), '--dry-run' => true])
        ->expectsOutputToContain('[Pt BR]')
        ->assertFailed();
});

it('writes the languages of a site with several, and reads them back unchanged', function (): void {
    lynx()->languages->update('default', ['code' => 'en', 'name' => 'English']);
    lynx()->languages->create('pt', 'Português', locale: 'pt-PT', fallback: 'en');
    $yaml = app(DraftWriter::class)->write();

    expect(Yaml::parse($yaml)['languages'])->toBe([
        'en' => ['name' => 'English', 'default' => true],
        'pt' => ['name' => 'Português', 'locale' => 'pt-PT', 'fallback' => 'en'],
    ]);

    $this->artisan('lynx:draft', ['path' => languageDraft($yaml), '--dry-run' => true])
        ->expectsOutputToContain('The site already matches the draft.')
        ->assertSuccessful();
});

it('writes no languages for a site with one', function (): void {
    expect(app(DraftWriter::class)->write())->not->toContain('languages:');
});

it('adds languages through the schema.draft tool', function (): void {
    lynx()->run(BuildDraft::class, ['draft' => "languages:\n  pt: Português\n"]);

    expect(lynx()->languages->find('pt'))->not->toBeNull();
});

it('rewrites every schema file when the languages change, as a renamed code changes every label', function (): void {
    lynx()->languages->create('pt', 'Português');
    lynx()->fields->create('summary', 'textarea', label: ['default' => 'Summary', 'pt' => 'Resumo']);
    $this->artisan('lynx:schema:export', ['--path' => $this->dir.'/schema'])->assertSuccessful();

    $this->artisan('lynx:draft', ['path' => languageDraft("languages:\n  en: { name: English, default: true }\n"), '--no-views' => true])->assertSuccessful();

    expect(File::exists($this->dir.'/schema/languages/default.yaml'))->toBeFalse()
        ->and(Yaml::parseFile($this->dir.'/schema/languages/en.yaml')['default'])->toBeTrue()
        ->and(Yaml::parseFile($this->dir.'/schema/fields/summary.yaml')['label'])->toBe(['en' => 'Summary', 'pt' => 'Resumo']);
});
