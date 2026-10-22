<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Actions\Content\ImportContent;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Pages\Page;
use LynxCms\Media\Actions\StoreMedia;

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images');
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [['slug' => 'still', 'fields' => [['name' => 'gallery']]]]]);
    lynx()->templates->update('course', ['add' => ['gallery', 'blocks']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
    $this->top = packagedImage(400, 300, 'top.jpg');
    $this->nested = packagedImage(200, 100, 'nested.jpg');
    lynx()->run(SavePage::class, ['id' => $this->alphaId, 'values' => [
        'gallery' => [['values' => $this->top + ['description' => ['default' => 'Shooting']]]],
        'blocks' => [['type' => 'still', 'values' => ['gallery' => [['values' => $this->nested]]]]],
    ]]);
    $this->package = sys_get_temp_dir().'/lynx-media-pkg-'.uniqid();
});

afterEach(function (): void {
    File::deleteDirectory($this->package);
});

/** @return array<string, mixed> a stored image entry on the alpha page */
function packagedImage(int $width, int $height, string $name): array
{
    return lynx()->run(StoreMedia::class, ['page' => test()->alphaId, 'field' => 'gallery', 'base64' => base64_encode(jpeg($width, $height)), 'filename' => $name])->value;
}

function packagedAlpha(): Page
{
    return lynx()->pages->get('/courses/alpha/') ?? throw new RuntimeException('No alpha page.');
}

it('copies the files a page\'s values use into the package', function (): void {
    app(ContentExporter::class)->export($this->package);

    expect(File::get($this->package.'/files/'.$this->top['file']))->toBe(Storage::disk('public')->get('lynx/'.$this->top['file']))
        ->and(File::exists($this->package.'/files/'.$this->nested['file']))->toBeTrue();
});

it('brings the files into a site that does not have the page', function (): void {
    app(ContentExporter::class)->export($this->package);
    lynx()->pages->delete('/courses/alpha/');
    expect(Storage::disk('public')->exists('lynx/'.$this->top['file']))->toBeFalse();

    lynx()->run(ImportContent::class, ['path' => $this->package]);

    $top = packagedAlpha()->gallery->first();
    $nested = packagedAlpha()->blocks->first()?->gallery->first();
    expect($top?->file())->toStartWith(packagedAlpha()->id.'/')
        ->and(Storage::disk('public')->get('lynx/'.$top?->file()))->toBe(File::get($this->package.'/files/'.$this->top['file']))
        ->and($top?->description())->toBe('Shooting')
        ->and($nested?->file())->toStartWith(packagedAlpha()->id.'/')
        ->and(Storage::disk('public')->exists('lynx/'.$nested?->file()))->toBeTrue();
});

it('reuses files the page already has when importing again', function (): void {
    app(ContentExporter::class)->export($this->package);
    lynx()->pages->delete('/courses/alpha/');
    lynx()->run(ImportContent::class, ['path' => $this->package]);
    $files = Storage::disk('public')->allFiles('lynx');

    lynx()->run(ImportContent::class, ['path' => $this->package]);

    expect(Storage::disk('public')->allFiles('lynx'))->toEqualCanonicalizing($files);
});

it('writes no files on a dry run', function (): void {
    app(ContentExporter::class)->export($this->package);
    lynx()->pages->delete('/courses/alpha/');

    lynx()->run(ImportContent::class, ['path' => $this->package], dryRun: true);

    expect(Storage::disk('public')->allFiles('lynx'))->toBe([]);
});

it('ignores package files outside the files folder', function (): void {
    app(ContentExporter::class)->export($this->package);
    lynx()->pages->delete('/courses/alpha/');
    File::put($this->package.'/outside.jpg', jpeg(10, 10));
    File::put($this->package.'/pages.jsonl', str_replace($this->top['file'], '1/../../outside.jpg', File::get($this->package.'/pages.jsonl')));

    $result = lynx()->run(ImportContent::class, ['path' => $this->package])->value;

    expect(Storage::disk('public')->allFiles())->not->toContain('lynx/'.packagedAlpha()->id.'/outside.jpg')
        ->and(implode(' ', $result['skipped']))->toContain('/courses/alpha/');
});
