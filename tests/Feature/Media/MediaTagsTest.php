<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Values\MediaList;
use LynxCms\Media\Values\Pagefile;

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('sheets', 'files', settings: ['tags' => ['default', 'pt', 'a4', 'letter', 'color', 'bw']]);
    lynx()->templates->update('course', ['add' => ['sheets']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;

    $sheet = fn (string $name, array $tags): array => ['values' => lynx()->run(StoreMedia::class, [
        'page' => $alpha->id, 'field' => 'sheets', 'base64' => base64_encode('%PDF-1.4 '.$name), 'filename' => $name.'.pdf',
    ])->value + ['tags' => $tags]];

    lynx()->run(SavePage::class, ['id' => $alpha->id, 'values' => ['sheets' => [
        $sheet('en-a4', ['default', 'a4', 'color']),
        $sheet('en-letter', ['default', 'letter', 'color']),
        $sheet('pt-a4', ['pt', 'a4', 'color']),
        $sheet('plain-bw', ['a4', 'bw']),
    ]]]);
});

/** @return list<string> the files' names without the random suffix */
function sheetNames(MediaList $files): array
{
    return array_map(fn (Pagefile $file): string => (string) preg_replace('/-[a-z0-9]{6}\.pdf$/', '', $file->name()), $files->all());
}

function sheets(): MediaList
{
    return lynx()->pages->get(test()->alphaId)->sheets;
}

/**
 * @param  Closure(): mixed  $read
 */
function inPortuguese(Closure $read): mixed
{
    return lynx()->language('pt', $read);
}

it('keeps the files that have every tag asked for', function (): void {
    expect(sheetNames(sheets()->withTags('a4', 'color')))->toBe(['en-a4', 'pt-a4'])
        ->and(sheetNames(sheets()->withTags('a4')))->toBe(['en-a4', 'pt-a4', 'plain-bw'])
        ->and(sheets()->withTags('poster')->isEmpty())->toBeTrue();
});

it('keeps the files of the current language', function (): void {
    expect(sheetNames(sheets()->inLanguage()))->toBe(['en-a4', 'en-letter'])
        ->and(inPortuguese(fn (): array => sheetNames(sheets()->inLanguage())))->toBe(['pt-a4'])
        ->and(sheetNames(sheets()->inLanguage('pt')))->toBe(['pt-a4']);
});

it('falls back along the language chain, then to files with no language', function (): void {
    expect(inPortuguese(fn (): array => sheetNames(sheets()->withTags('letter')->inLanguage())))->toBe(['en-letter'])
        ->and(inPortuguese(fn (): array => sheetNames(sheets()->withTags('bw')->inLanguage())))->toBe(['plain-bw'])
        ->and(inPortuguese(fn (): string => (string) sheets()->withTags('a4', 'color')->inLanguage()->first()?->name()))->toStartWith('pt-a4');
});
