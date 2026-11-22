<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use LynxCms\Admin\Http\UseAdminLocale;
use LynxCms\Admin\Locale\SaveAdminLocale;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Languages\EditorLanguage;
use LynxCms\Core\Schema\SchemaExporter;
use LynxCms\Core\Schema\SchemaWording;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Finder\Catalog\ConditionCatalog;
use LynxCms\Forms\Actions\InstallForms;
use LynxCms\Forms\FormSchema;

/*
 * Labels, descriptions and notes follow the language the editor reads the admin in (ADR-0140):
 * the text stored in that language, then a translation file, then the text as the site shows it.
 * What visitors read, and what is saved, stays in the content language.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('level', 'choice', settings: [
        'options' => [['value' => 'basic', 'label' => ['default' => 'Basic', 'pt' => 'Básico']]],
        'description' => ['default' => 'Who it is for', 'pt' => 'A quem se destina'],
        'notes' => ['default' => 'One of the list', 'pt' => 'Um da lista'],
    ]);
    lynx()->fields->update('level', ['label' => ['default' => 'Level', 'pt' => 'Nível']]);
    lynx()->fields->update('price', ['label' => 'Cost']);
    lynx()->templates->update('course', ['label' => ['default' => 'Course', 'pt' => 'Curso'], 'add' => [['name' => 'level']]]);
    $this->lang = testingPath('editor-lang');
    File::ensureDirectoryExists($this->lang);
    app('translation.loader')->addPath($this->lang);
});

afterEach(function (): void {
    File::deleteDirectory($this->lang);
});

function readAs(string $locale): void
{
    app()->setLocale($locale);
    app(EditorLanguage::class)->use($locale);
}

/** @param  array<string, string>  $lines  English text => translation */
function schemaLines(string $path, array $lines): void
{
    File::ensureDirectoryExists(dirname(test()->lang.'/'.$path));
    File::put(test()->lang.'/'.$path, '<?php return '.var_export($lines, true).';');
}

/** @return list<string> */
function levelTexts(): array
{
    $field = lynx()->templates->fields('course')->get('level');

    return [$field->label(), $field->description(), $field->notes(), lynx()->fields->get('level')->label, lynx()->templates->get('course')->label];
}

it('reads labels in the language of the site that matches the editor\'s', function (): void {
    readAs('pt');

    expect(levelTexts())->toBe(['Nível', 'A quem se destina', 'Um da lista', 'Nível', 'Curso']);
});

it('reads labels in the content language without an editor', function (): void {
    expect(levelTexts())->toBe(['Level', 'Who it is for', 'One of the list', 'Level', 'Course'])
        ->and(lynx()->language('pt', levelTexts(...)))->toBe(['Nível', 'A quem se destina', 'Um da lista', 'Nível', 'Curso']);
});

it('prefers the closest variant of the editor\'s language', function (string $locale, string $expected): void {
    lynx()->languages->add('pt-br', 'Brasil', locale: 'pt-BR', fallback: 'pt');
    lynx()->fields->update('level', ['label' => ['default' => 'Level', 'pt' => 'Nível', 'pt-br' => 'Nível BR']]);
    readAs($locale);

    expect(lynx()->fields->get('level')->label)->toBe($expected);
})->with([['pt-BR', 'Nível BR'], ['pt_BR', 'Nível BR'], ['pt-PT', 'Nível'], ['pt', 'Nível']]);

it('reads a variant the site lacks from the language it has', function (): void {
    readAs('pt-BR');

    expect(lynx()->fields->get('level')->label)->toBe('Nível');
});

it('reads site labels from the app\'s file in a language the site does not publish', function (): void {
    schemaLines('et/lynx-schema.php', ['Level' => 'Tase', 'Cost' => 'Hind', 'Who it is for' => 'Kellele']);
    readAs('et');

    expect(lynx()->fields->get('level')->label)->toBe('Tase')
        ->and(lynx()->fields->get('price')->label)->toBe('Hind')
        ->and(levelTexts()[1])->toBe('Kellele');
});

it('keeps the site\'s text where no translation exists', function (): void {
    readAs('et');

    expect(levelTexts())->toBe(['Level', 'Who it is for', 'One of the list', 'Level', 'Course']);
});

it('reads a module\'s labels from its own files, and only its own', function (): void {
    app(SchemaWording::class)->register('lynx-shop', fields: ['price']);
    app('translator')->addNamespace('lynx-shop', $this->lang.'/shop');
    schemaLines('shop/et/schema.php', ['Cost' => 'Hind', 'Level' => 'Vale']);
    readAs('et');

    expect(lynx()->fields->get('price')->label)->toBe('Hind')
        ->and(lynx()->fields->get('level')->label)->toBe('Level');
});

it('keeps English labels for an English editor, whatever the app\'s fallback', function (): void {
    schemaLines('pt/lynx-schema.php', ['Cost' => 'Custo']);
    app()->setFallbackLocale('pt');
    readAs('en');

    expect(lynx()->fields->get('price')->label)->toBe('Cost');
});

it('keeps a module\'s label the site reworded', function (): void {
    app(SchemaWording::class)->register('lynx-shop', fields: ['price']);
    app('translator')->addNamespace('lynx-shop', $this->lang.'/shop');
    schemaLines('shop/et/schema.php', ['Cost' => 'Hind']);
    lynx()->fields->update('price', ['label' => 'Fee']);
    readAs('et');

    expect(lynx()->fields->get('price')->label)->toBe('Fee');
});

it('keeps what visitors read in the content language', function (): void {
    $course = createCourses('alpha')['alpha'];
    $course->level = 'basic';
    $course->save();
    readAs('pt');

    expect((string) lynx()->pages->get($course->id)->level)->toBe('Basic')
        ->and((string) LocalizedText::from(['default' => 'Hello', 'pt' => 'Olá']))->toBe('Hello');
});

it('exports the schema as stored', function (): void {
    schemaLines('et/lynx-schema.php', ['Cost' => 'Hind']);
    app(SchemaExporter::class)->export($this->lang.'/before');
    readAs('et');
    app(SchemaExporter::class)->export($this->lang.'/after');
    $read = fn (string $folder): array => collect(File::allFiles("{$this->lang}/{$folder}"))->mapWithKeys(fn (SplFileInfo $file): array => [$file->getFilename() => File::get($file->getPathname())])->sortKeys()->all();

    expect($read('after'))->toBe($read('before'));
});

it('does not take a translated label for a changed one when a module installs again', function (): void {
    lynx()->run(InstallForms::class);
    $field = array_key_first(array_diff_key(FormSchema::fields(), [FormSchema::PICKER => true]));
    $stored = lynx()->fields->get($field)->labels()->toStorage();
    schemaLines('et/lynx-schema.php', [$stored => 'Tõlgitud']);
    readAs('et');

    expect(lynx()->fields->get($field)->label)->toBe('Tõlgitud')
        ->and(lynx()->run(InstallForms::class, dryRun: true)->changes->all())->toBe([])
        ->and(lynx()->fields->get($field)->labels()->toStorage())->toBe($stored);
});

it('sets the editor\'s language on admin requests, for that request only', function (): void {
    config(['lynx-admin.locales' => ['en' => 'English', 'pt' => 'Português']]);
    lynx()->actingAs(Actor::user(1), fn (): mixed => lynx()->run(SaveAdminLocale::class, ['locale' => 'pt']));
    $request = Request::create('/lynx');
    $request->setUserResolver(fn (): GenericUser => new GenericUser(['id' => 1]));
    $inside = null;
    app(UseAdminLocale::class)->handle($request, function () use (&$inside): ResponseFactory|Response {
        $inside = levelTexts()[0];

        return response('');
    });
    app()->forgetScopedInstances();

    expect($inside)->toBe('Nível')
        ->and(levelTexts()[0])->toBe('Level');
});

it('shows the page editor and the finder in the editor\'s language', function (): void {
    config(['lynx-admin.locales' => ['en' => 'English', 'pt' => 'Português']]);
    $course = createCourses('alpha')['alpha'];
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->actingAs(Actor::user(1), fn (): mixed => lynx()->run(SaveAdminLocale::class, ['locale' => 'pt']));

    $this->get(route('lynx.admin.pages.edit', $course->id))->assertOk()->assertSee('Nível')->assertSee('A quem se destina')->assertSee('Básico');
    readAs('pt');
    expect(app(ConditionCatalog::class)->find('level')?->label)->toBe('Nível')
        ->and(app(ConditionCatalog::class)->find('level')?->options)->toBe(['basic' => 'Básico']);
});
