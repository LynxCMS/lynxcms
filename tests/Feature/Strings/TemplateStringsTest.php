<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Content\ImportContent;
use LynxCms\Core\Actions\Strings\SaveStrings;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Strings\StringCatalog;
use LynxCms\Core\Strings\StringScanner;

beforeEach(function (): void {
    courseSite();
    View::addLocation(__DIR__.'/../../Fixtures/views');
    lynx()->languages->add('pt-br', 'Português (Brasil)', fallback: 'pt');
    lynx()->templates->add('landing', fields: ['title']);
    $page = lynx()->pages->new('landing', '/');
    $page->name = 'landing';
    $page->title = 'Landing';
    $page->save();
    $this->landing = lynx()->pages->get('/landing/');
});

function landing(?string $language = null): string
{
    $render = fn (): string => view('templates.landing', ['page' => lynx()->pages->get('/landing/')])->render();

    return $language === null ? $render() : lynx()->language($language, $render);
}

function saveStrings(array $input, bool $dryRun = false): mixed
{
    return lynx()->run(SaveStrings::class, $input + ['page' => test()->landing->id], dryRun: $dryRun);
}

it('finds the strings a template and its layout and partials declare', function (): void {
    $strings = app(StringCatalog::class)->forTemplate('landing');

    expect(array_keys($strings))->toEqualCanonicalizing(['hero.title', 'hero.intro', 'cta.label', 'footer.note'])
        ->and($strings['hero.title']->label)->toBe('Hero title')
        ->and($strings['hero.title']->default)->toBe(['default' => 'Audiovisual productions', 'pt' => 'Produções audiovisuais'])
        ->and($strings['hero.intro']->format)->toBe('markdown')
        ->and($strings['footer.note']->scope)->toBe('site');
});

it('prints the code defaults, per language, escaped', function (): void {
    expect(landing())->toContain('<h1>Audiovisual productions</h1>')->toContain('Contact &lt;us&gt;')->toContain('<strong>images</strong>')
        ->and(landing('pt'))->toContain('<h1>Produções audiovisuais</h1>')
        ->and(landing('pt-br'))->toContain('<h1>Produções audiovisuais</h1>');
});

it('prints edits, falling back along the language chain', function (): void {
    saveStrings(['language' => 'pt', 'values' => ['hero.title' => 'Filmes e multimédia', 'cta.label' => 'Fale connosco']]);

    expect(landing('pt'))->toContain('<h1>Filmes e multimédia</h1>')->toContain('Fale connosco')
        ->and(landing('pt-br'))->toContain('<h1>Filmes e multimédia</h1>')
        ->and(landing())->toContain('<h1>Audiovisual productions</h1>');
});

it('stores only edits and resets to the code text with null', function (): void {
    saveStrings(['values' => ['cta.label' => 'Write to us']]);
    expect(DB::table('lynx_strings')->count())->toBe(1);

    saveStrings(['values' => ['cta.label' => null]]);
    expect(DB::table('lynx_strings')->count())->toBe(0)->and(landing())->toContain('Contact &lt;us&gt;');
});

it('keeps site strings on the home page, shared by every page', function (): void {
    saveStrings(['values' => ['footer.note' => 'Esfera Cúbica, 2026']]);

    expect(landing())->toContain('Esfera Cúbica, 2026')
        ->and(DB::table('lynx_strings')->value('page_id'))->toBe(lynx()->pages->get('/')?->id);
});

it('refuses keys the template does not declare', function (): void {
    saveStrings(['values' => ['nope' => 'x']]);
})->throws(ValidationException::class, 'nope');

it('writes nothing on a dry run and rolls back', function (): void {
    saveStrings(['values' => ['cta.label' => 'x']], dryRun: true);
    expect(DB::table('lynx_strings')->count())->toBe(0);

    lynx()->hook(SaveStrings::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): mixed => saveStrings(['values' => ['cta.label' => 'x']]))->toThrow(ActionHalted::class)
        ->and(DB::table('lynx_strings')->count())->toBe(0);
});

it('reports keys that are not literal and keys declared twice with different defaults', function (): void {
    $report = app(StringScanner::class)->scan(__DIR__.'/../../Fixtures/views/templates/strings-bad.blade.php');

    expect($report->errors)->toHaveCount(1)
        ->and($report->errors[0])->toContain('literal')
        ->and(app(StringCatalog::class)->conflicts(['landing', 'strings-bad']))->toHaveKey('cta.label');
});

it('scans again when a view changes', function (): void {
    $dir = sys_get_temp_dir().'/lynx-strings-'.uniqid();
    File::ensureDirectoryExists($dir.'/templates');
    File::put($dir.'/templates/notes.blade.php', "@string('notes.one', 'One')");
    View::prependLocation($dir);
    lynx()->templates->add('notes', fields: ['title']);

    expect(app(StringCatalog::class)->forTemplate('notes'))->toHaveKeys(['notes.one']);

    File::put($dir.'/templates/notes.blade.php', "@string('notes.one', 'One') @string('notes.two', 'Two')");
    touch($dir.'/templates/notes.blade.php', time() + 5);

    expect(app(StringCatalog::class)->forTemplate('notes'))->toHaveKeys(['notes.one', 'notes.two']);
    File::deleteDirectory($dir);
});

it('removes a page\'s edits with the page', function (): void {
    saveStrings(['values' => ['cta.label' => 'x']]);
    lynx()->pages->delete('/landing/');

    expect(DB::table('lynx_strings')->count())->toBe(0);
});

it('scans from Artisan', function (): void {
    // Lists templates; the exit code also reflects the workbench's own views, so it is not asserted here.
    $this->artisan('lynx:strings:scan')->expectsOutputToContain('landing');
});

it('lists only templates with a view, and counts the others', function (): void {
    lynx()->templates->add('shop_settings', fields: ['title'], hasUrl: false, singleton: true);

    expect(app(StringCatalog::class)->hasView('landing'))->toBeTrue()
        ->and(app(StringCatalog::class)->hasView('shop_settings'))->toBeFalse();

    $this->artisan('lynx:strings:scan')
        ->doesntExpectOutputToContain('shop_settings')
        ->expectsOutputToContain('have no view, so they declare no strings');
});

it('shows agents the strings of a page with their edits', function (): void {
    saveStrings(['language' => 'pt', 'values' => ['cta.label' => 'Fale connosco']]);
    $strings = collect(app(AgentToolkit::class)->getPage('/landing/')['strings'])->keyBy('key');

    expect($strings['cta.label']['edits'])->toBe(['pt' => 'Fale connosco'])
        ->and($strings['footer.note']['scope'])->toBe('site');
});

it('moves edited strings with content packages', function (): void {
    saveStrings(['language' => 'pt', 'values' => ['cta.label' => 'Fale connosco']]);
    $dir = sys_get_temp_dir().'/lynx-strings-pkg-'.uniqid();
    app(ContentExporter::class)->export($dir);
    saveStrings(['language' => 'pt', 'values' => ['cta.label' => null]]);

    lynx()->run(ImportContent::class, ['path' => $dir]);
    File::deleteDirectory($dir);

    expect(landing('pt'))->toContain('Fale connosco');
});

it('edits site strings from any page, the home page included', function (): void {
    lynx()->run(SaveStrings::class, ['page' => '/', 'values' => ['footer.note' => 'Esfera Cúbica']]);

    expect(landing())->toContain('Esfera Cúbica');
});

it('reports code texts given for language codes the site does not have', function (): void {
    $dir = sys_get_temp_dir().'/lynx-strings-'.uniqid();
    File::ensureDirectoryExists($dir.'/templates');
    File::put($dir.'/templates/promo.blade.php', "@string('promo.title', ['pt' => 'Promoção', 'en' => 'Promotion'])");
    View::prependLocation($dir);
    lynx()->templates->add('promo', fields: ['title']);

    expect(app(StringCatalog::class)->unknownLanguages(['promo', 'landing']))->toBe(['promo.title' => ['en']]);
    $this->artisan('lynx:strings:scan')->expectsOutputToContain('[promo.title] has text for [en]')->assertExitCode(1);
    File::deleteDirectory($dir);
});

it('makes strings in layouts site strings unless they say otherwise', function (): void {
    $dir = sys_get_temp_dir().'/lynx-strings-'.uniqid();
    File::ensureDirectoryExists($dir.'/layouts');
    File::ensureDirectoryExists($dir.'/templates');
    File::put($dir.'/layouts/plain.blade.php', "<footer>@string('footer.thanks', 'Thanks') @string('footer.page', 'Per page', scope: 'page')</footer>@yield('main')");
    File::put($dir.'/templates/promo.blade.php', "@extends('layouts.plain') @section('main')@string('promo.title', 'Promo')@endsection");
    View::prependLocation($dir);
    lynx()->templates->add('promo', fields: ['title']);
    $promo = lynx()->pages->new('promo', '/');
    $promo->name = 'promo';
    $promo->title = 'Promo';
    $promo->save();

    $strings = app(StringCatalog::class)->forTemplate('promo');
    expect($strings['footer.thanks']->scope)->toBe('site')
        ->and($strings['footer.page']->scope)->toBe('page')
        ->and($strings['promo.title']->scope)->toBe('page');

    lynx()->run(SaveStrings::class, ['page' => '/promo/', 'values' => ['footer.thanks' => 'Obrigado']]);

    expect(DB::table('lynx_strings')->value('page_id'))->toBe(lynx()->pages->get('/')?->id)
        ->and(view('templates.promo', ['page' => lynx()->pages->get('/promo/')])->render())->toContain('Obrigado');
    File::deleteDirectory($dir);
});

it('scans again when the folders of site strings change', function (): void {
    $dir = sys_get_temp_dir().'/lynx-strings-'.uniqid();
    File::ensureDirectoryExists($dir.'/layouts');
    File::ensureDirectoryExists($dir.'/templates');
    File::put($dir.'/layouts/plain.blade.php', "@string('footer.thanks', 'Thanks')");
    File::put($dir.'/templates/promo.blade.php', "@extends('layouts.plain')");
    View::prependLocation($dir);
    lynx()->templates->add('promo', fields: ['title']);

    config(['lynx.strings.site_folders' => ['nowhere']]);
    expect(app(StringCatalog::class)->forTemplate('promo')['footer.thanks']->scope)->toBe('page');

    config(['lynx.strings.site_folders' => ['layouts']]);
    app()->forgetInstance(StringCatalog::class);

    expect(app(StringCatalog::class)->forTemplate('promo')['footer.thanks']->scope)->toBe('site');
    File::deleteDirectory($dir);
});

it('reads its cache with a store that refuses to unserialize objects', function (): void {
    // Laravel 13's default: cache.serializable_classes = false turns cached objects into incomplete ones.
    config([
        'cache.default' => 'file',
        'cache.stores.file.path' => sys_get_temp_dir().'/lynx-strings-cache-'.uniqid(),
        'cache.serializable_classes' => false,
    ]);
    app()->forgetInstance('cache');
    app()->forgetInstance('cache.store');
    Cache::clearResolvedInstances();
    app()->forgetInstance(StringCatalog::class);
    app(StringCatalog::class)->forTemplate('landing');
    app()->forgetInstance(StringCatalog::class);

    $strings = app(StringCatalog::class)->forTemplate('landing');

    expect($strings['hero.title']->label)->toBe('Hero title')->and($strings['footer.note']->isSite())->toBeTrue();
});
