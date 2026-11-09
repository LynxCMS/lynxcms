<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Media\Actions\StoreMedia;

/*
 * pages.duplicate stores the copy's images and files again in its own folder, so deleting the
 * original (which deletes its folder) leaves the copy whole.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images');
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [['slug' => 'still', 'fields' => [['name' => 'gallery']]]]]);
    lynx()->templates->update('course', ['add' => ['gallery', 'blocks']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $store = fn (string $name): array => lynx()->run(StoreMedia::class, ['page' => (int) $alpha->id, 'field' => 'gallery', 'base64' => base64_encode(jpeg(40, 30)), 'filename' => $name])->value;
    lynx()->run(SavePage::class, ['id' => (int) $alpha->id, 'values' => [
        'gallery' => [['values' => $store('top.jpg')]],
        'blocks' => [['type' => 'still', 'values' => ['gallery' => [['values' => $store('nested.jpg')]]]]],
    ]]);
});

it('copies the files into the copy\'s own folder', function (): void {
    $copy = lynx()->pages->duplicate('/courses/alpha/');
    lynx()->pages->delete('/courses/alpha/');
    $copy = lynx()->pages->get((int) $copy->id);

    $top = $copy->gallery->first();
    $nested = $copy->blocks->first()?->gallery->first();

    expect($top?->file())->toStartWith($copy->id.'/')
        ->and(Storage::disk('public')->exists('lynx/'.$top?->file()))->toBeTrue()
        ->and($nested?->file())->toStartWith($copy->id.'/')
        ->and(Storage::disk('public')->exists('lynx/'.$nested?->file()))->toBeTrue();
});

it('stores no files on a dry run', function (): void {
    $before = Storage::disk('public')->allFiles();

    lynx()->pages->duplicate('/courses/alpha/', dryRun: true);

    expect(Storage::disk('public')->allFiles())->toBe($before);
});
