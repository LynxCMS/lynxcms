<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Pages\Page;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Admin\Livewire\MediaField;
use LynxCms\Media\Admin\MediaConverter;
use LynxCms\Media\Values\MediaList;
use LynxCms\Media\Values\Pagefile;

/*
 * A file per language (ADR-0150): with per_language on, each file has a language, or none for
 * every language. Templates read the current language's files, falling back along the language
 * chain, then to the files for every language. A single-file field returns one of them.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('hero', 'images', settings: ['max_files' => 1, 'per_language' => true]);
    lynx()->fields->add('brochures', 'files', settings: ['per_language' => true]);
    lynx()->templates->update('course', ['add' => ['hero', 'brochures']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
});

/** One stored file's item, with its language (null for every language). */
function languageFile(string $field, string $name, ?string $language): array
{
    $isImage = $field === 'hero';
    $contents = $isImage ? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true) : '%PDF-1.4 '.$name;
    $values = lynx()->run(StoreMedia::class, [
        'page' => test()->alphaId, 'field' => $field, 'base64' => base64_encode($contents), 'filename' => $name.($isImage ? '.png' : '.pdf'),
    ])->value;

    return ['values' => $values + array_filter(['language' => $language])];
}

/** @param  list<array<string, mixed>>  $items */
function saveFiles(string $field, array $items): void
{
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => [$field => $items]]);
}

function fileStem(?Pagefile $file): ?string
{
    return $file === null ? null : (string) preg_replace('/-[a-z0-9]{6}\.(png|pdf)$/', '', $file->name());
}

/** @return list<string|null> */
function stems(MediaList $files): array
{
    return array_map(fileStem(...), $files->all());
}

function alphaPage(): Page
{
    return lynx()->pages->get(test()->alphaId) ?? throw new RuntimeException('No alpha.');
}

it('gives a single-image field the current language\'s image', function (): void {
    saveFiles('hero', [languageFile('hero', 'hero-en', 'default'), languageFile('hero', 'hero-pt', 'pt')]);

    expect(fileStem(alphaPage()->hero))->toBe('hero-en')
        ->and(lynx()->language('pt', fn (): ?string => fileStem(alphaPage()->hero)))->toBe('hero-pt');
});

it('falls back along the language chain, then to the image for every language', function (): void {
    saveFiles('hero', [languageFile('hero', 'hero-en', 'default')]);

    expect(lynx()->language('pt', fn (): ?string => fileStem(alphaPage()->hero)))->toBe('hero-en');

    saveFiles('hero', [languageFile('hero', 'hero-all', null), languageFile('hero', 'hero-pt', 'pt')]);

    expect(fileStem(alphaPage()->hero))->toBe('hero-all')
        ->and(lynx()->language('pt', fn (): ?string => fileStem(alphaPage()->hero)))->toBe('hero-pt');
});

it('shows nothing in a language without its own file when the field does not fall back', function (): void {
    lynx()->fields->update('hero', ['settings' => ['fallback' => 'none']]);
    saveFiles('hero', [languageFile('hero', 'hero-en', 'default')]);

    expect(lynx()->language('pt', fn (): mixed => alphaPage()->hero))->toBeNull();
});

it('lists only the current language\'s files, with the files for every language', function (): void {
    saveFiles('brochures', [languageFile('brochures', 'guide-en', 'default'), languageFile('brochures', 'guide-pt', 'pt'), languageFile('brochures', 'terms', null)]);

    expect(stems(alphaPage()->brochures))->toBe(['guide-en', 'terms'])
        ->and(lynx()->language('pt', fn (): array => stems(alphaPage()->brochures)))->toBe(['guide-pt', 'terms'])
        ->and(alphaPage()->getUnformatted('brochures'))->toHaveCount(3);
});

it('counts max_files per language', function (): void {
    saveFiles('hero', [languageFile('hero', 'hero-en', 'default'), languageFile('hero', 'hero-pt', 'pt'), languageFile('hero', 'hero-all', null)]);

    expect(alphaPage()->getUnformatted('hero'))->toHaveCount(3)
        ->and(fn () => saveFiles('hero', [languageFile('hero', 'one', 'pt'), languageFile('hero', 'two', 'pt')]))->toThrow(ValidationException::class);
});

it('refuses a language the site lacks', function (): void {
    saveFiles('hero', [languageFile('hero', 'hero-fr', 'fr')]);
})->throws(ValidationException::class);

it('keeps every file for every language while the field is not per language', function (): void {
    lynx()->fields->update('brochures', ['settings' => ['per_language' => false]]);
    saveFiles('brochures', [languageFile('brochures', 'guide-en', 'default'), languageFile('brochures', 'guide-pt', 'pt')]);

    expect(lynx()->language('pt', fn (): array => stems(alphaPage()->brochures)))->toBe(['guide-en', 'guide-pt']);
});

it('stores a file in a language with media.store', function (): void {
    $values = lynx()->run(StoreMedia::class, ['page' => $this->alphaId, 'field' => 'brochures', 'base64' => base64_encode('%PDF-1.4 x'), 'filename' => 'x.pdf', 'language' => 'pt'])->value;

    expect($values['language'] ?? null)->toBe('pt');
});

it('finds pages by a file\'s language', function (): void {
    saveFiles('brochures', [languageFile('brochures', 'guide-pt', 'pt')]);

    expect(lynx()->pages->count('template=course, brochures.language=pt'))->toBe(1)
        ->and(lynx()->pages->count('template=course, brochures.language=default'))->toBe(0);
});

function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true);
}

it('shows a tab per language in the editor, with the language\'s files and the shared ones apart', function (): void {
    test()->actingAs(new GenericUser(['id' => 1]));
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);

    $component = Livewire::test(MediaField::class, ['pageId' => test()->alphaId, 'field' => 'brochures'])
        ->assertSeeHtml('data-media-languages')
        ->assertSet('language', 'default')
        ->assertSeeHtml('data-media-group="language"')
        ->assertSeeHtml('data-media-group="shared"')
        ->assertDontSeeHtml('data-media-language=""')
        ->set('language', 'pt')
        ->set('uploads', [UploadedFile::fake()->createWithContent('guia.pdf', '%PDF-1.4 guia')])
        ->set('sharedUploads', [UploadedFile::fake()->createWithContent('terms.pdf', '%PDF-1.4 terms')]);

    expect(array_column($component->get('items'), 'language'))->toBe(['pt', '']);

    $component->set('language', 'default')->assertSeeHtml('data-media-fallback');
});

it('says what visitors see in a tab without files of its own', function (): void {
    test()->actingAs(new GenericUser(['id' => 1]));
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    saveFiles('brochures', [languageFile('brochures', 'guide-en', 'default')]);
    $items = app(MediaConverter::class)->toForm(alphaPage()->getUnformatted('brochures'));

    Livewire::test(MediaField::class, ['pageId' => test()->alphaId, 'field' => 'brochures', 'items' => $items])
        ->assertDontSeeHtml('data-media-fallback')
        ->set('language', 'pt')
        ->assertSeeHtml('data-media-fallback');
});

it('sorts a file within its group', function (): void {
    test()->actingAs(new GenericUser(['id' => 1]));
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    saveFiles('brochures', [languageFile('brochures', 'a-pt', 'pt'), languageFile('brochures', 'shared', null), languageFile('brochures', 'b-pt', 'pt')]);
    $items = app(MediaConverter::class)->toForm(alphaPage()->getUnformatted('brochures'));
    [$a, $shared, $b] = array_column($items, 'key');

    $component = Livewire::test(MediaField::class, ['pageId' => test()->alphaId, 'field' => 'brochures', 'items' => $items])
        ->set('language', 'pt')
        ->call('sort', $b, 0);

    expect(array_column($component->get('items'), 'key'))->toBe([$b, $a, $shared]);
});

it('shows no language tabs on a field that is not per language', function (): void {
    test()->actingAs(new GenericUser(['id' => 1]));
    lynx()->fields->update('brochures', ['settings' => ['per_language' => false]]);

    Livewire::test(MediaField::class, ['pageId' => test()->alphaId, 'field' => 'brochures'])
        ->assertDontSeeHtml('data-media-languages');
});

it('titles the language\'s group in the singular on a field with one file', function (): void {
    test()->actingAs(new GenericUser(['id' => 1]));
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $label = lynx()->languages->default()->tabLabel();

    Livewire::test(MediaField::class, ['pageId' => test()->alphaId, 'field' => 'hero'])
        ->assertSeeHtml('data-media-group-title>'.e(trans_choice('lynx-media::admin.language_images', 1, ['language' => $label])).'<');
    Livewire::test(MediaField::class, ['pageId' => test()->alphaId, 'field' => 'brochures'])
        ->assertSeeHtml('data-media-group-title>'.e(trans_choice('lynx-media::admin.language_files', 2, ['language' => $label])).'<');
});
