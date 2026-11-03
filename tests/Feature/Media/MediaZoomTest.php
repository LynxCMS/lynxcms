<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Livewire\Livewire;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Admin\Livewire\MediaField;
use LynxCms\Media\Admin\MediaConverter;
use LynxCms\Media\MediaPaths;

/*
 * Each picture in an images field has a zoom button that shows it at full size, uncropped, in a
 * dialog: also on a field the editor may only look at. Plain files have none.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images', settings: ['max_files' => 3]);
    lynx()->fields->add('downloads', 'files');
    lynx()->templates->update('course', ['add' => ['gallery', 'downloads']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('gives every picture a zoom button that opens it at full size', function (): void {
    $component = Livewire::test(MediaField::class, ['pageId' => $this->alphaId, 'field' => 'gallery'])
        ->set('uploads', [UploadedFile::fake()->createWithContent('a.jpg', jpeg(300, 200)), UploadedFile::fake()->createWithContent('b.jpg', jpeg(300, 200))]);
    $html = $component->html();

    expect(substr_count($html, 'data-media-zoom'))->toBe(2)
        ->and($html)->toContain('data-media-viewer');

    foreach ($component->get('items') as $item) {
        expect($html)->toContain(Js::from(app(MediaPaths::class)->url($item['file']))->toHtml());
    }
});

it('lets an editor zoom on a field they may only look at', function (): void {
    $entry = lynx()->run(StoreMedia::class, ['page' => $this->alphaId, 'field' => 'gallery', 'base64' => base64_encode(jpeg(300, 200)), 'filename' => 'a.jpg'])->value;
    // The page editor hands the field its items, as form items.
    $items = app(MediaConverter::class)->toForm([['key' => 'a', 'values' => $entry]]);

    $html = Livewire::test(MediaField::class, ['pageId' => $this->alphaId, 'field' => 'gallery', 'items' => $items, 'disabled' => true])->html();

    expect(substr_count($html, 'data-media-zoom'))->toBe(1);
});

it('has no zoom for plain files', function (): void {
    $html = Livewire::test(MediaField::class, ['pageId' => $this->alphaId, 'field' => 'downloads'])
        ->set('uploads', [UploadedFile::fake()->createWithContent('notes.pdf', '%PDF-1.4 test')])
        ->html();

    expect($html)->not->toContain('data-media-zoom');
});
