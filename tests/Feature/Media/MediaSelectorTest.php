<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Media\Actions\StoreMedia;

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images', settings: ['tags' => ['hero', 'detail']]);
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [['slug' => 'still', 'fields' => [['name' => 'gallery'], ['name' => 'summary']]]]]);
    lynx()->templates->update('course', ['add' => ['gallery', 'blocks']]);
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = createCourses('alpha', 'beta', 'gamma');

    saveMedia($alpha, ['gallery' => [
        ['values' => still($alpha, 1600, 900) + ['tags' => ['hero'], 'description' => ['default' => 'Shooting', 'pt' => 'Rodagem']]],
        ['enabled' => false, 'values' => still($alpha, 300, 200) + ['tags' => ['detail']]],
    ]]);
    saveMedia($beta, ['gallery' => [['values' => still($beta, 400, 300) + ['tags' => ['detail']]]]]);
    saveMedia($gamma, ['blocks' => [
        ['type' => 'still', 'values' => ['gallery' => [['values' => still($gamma, 800, 600) + ['tags' => ['hero'], 'description' => ['pt' => 'Making of']]]]]],
        ['type' => 'still', 'enabled' => false, 'values' => ['gallery' => [['values' => still($gamma, 200, 100) + ['tags' => ['detail']]]]]],
    ]]);
});

/** @return array<string, mixed> a stored image entry of the given size */
function still(Page $page, int $width, int $height): array
{
    return lynx()->run(StoreMedia::class, ['page' => $page->id, 'field' => 'gallery', 'base64' => base64_encode(jpeg($width, $height)), 'filename' => 'still.jpg'])->value;
}

/** @param array<string, mixed> $values */
function saveMedia(Page $page, array $values): void
{
    lynx()->run(SavePage::class, ['id' => $page->id, 'values' => $values]);
}

/** @return list<string> */
function withMedia(string $selector, string $language = 'default'): array
{
    return lynx()->language($language, fn (): array => lynx()->pages->find('template=course, sort=name, '.$selector)->map(fn (Page $page): string => $page->name()));
}

it('finds pages by the tags of their visible images', function (): void {
    expect(withMedia('gallery.tags=hero'))->toBe(['alpha'])
        ->and(withMedia('gallery.tags=detail'))->toBe(['beta'])
        ->and(withMedia('gallery.tags=hero|detail'))->toBe(['alpha', 'beta'])
        ->and(withMedia('gallery.tags!=hero'))->toBe(['beta', 'gamma']);
});

it('compares image sizes and types', function (): void {
    expect(withMedia('gallery.width>=1000'))->toBe(['alpha'])
        ->and(withMedia('gallery.height<500'))->toBe(['beta'])
        ->and(withMedia('gallery.mime=image/jpeg'))->toBe(['alpha', 'beta'])
        ->and(withMedia('gallery.file%=still'))->toBe(['alpha', 'beta']);
});

it('matches descriptions in the current language, with fallback', function (): void {
    expect(withMedia('gallery.description%=Rodagem', 'pt'))->toBe(['alpha'])
        ->and(withMedia('gallery.description%=Shooting'))->toBe(['alpha'])
        ->and(withMedia('gallery.description%=Rodagem'))->toBe([]);
});

it('looks inside images nested in items', function (): void {
    expect(withMedia('blocks.gallery.count>0'))->toBe(['gamma'])
        ->and(withMedia('blocks.gallery.tags=hero'))->toBe(['gamma'])
        ->and(withMedia('blocks.gallery.tags=detail'))->toBe([])
        ->and(withMedia('blocks.gallery.width=800'))->toBe(['gamma'])
        ->and(withMedia('blocks.gallery.description%=Making', 'pt'))->toBe(['gamma'])
        ->and(withMedia('blocks.gallery.tags!=hero'))->toBe(['alpha', 'beta']);
});

it('explains selectors it cannot run', function (string $selector, string $message): void {
    expect(fn (): array => withMedia($selector))->toThrow(SelectorException::class, $message);
})->with([
    'unknown property' => ['gallery.colour=red', 'no subfield [colour]'],
    'unknown nested property' => ['blocks.gallery.colour=red', '[colour]'],
    'nesting into a plain subfield' => ['blocks.summary.count>0', '[summary]'],
    'ordering a list' => ['gallery.tags>a', 'use = or !='],
]);
