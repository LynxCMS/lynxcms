<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Media\Admin\Livewire\MediaField;

/*
 * The upload zone (lynxUploadZone in the admin's JS) takes files dropped on it as well as chosen
 * (issue #223), and a field with one file takes the first. Before uploading, an images field's
 * JPEG, PNG and WebP files larger than its maximum are scaled down in the browser to that maximum,
 * at the site's quality, so a phone's photo fits the upload limit; the server still checks and
 * scales every file, as uploads from MCP clients and the agent never pass the browser.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images', settings: ['max_files' => 3, 'max_width' => 1600, 'max_height' => 1200]);
    lynx()->fields->add('cover', 'images', settings: ['max_files' => 1]);
    lynx()->fields->add('brochure', 'files');
    lynx()->templates->update('course', ['add' => ['gallery', 'cover', 'brochure']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('takes files chosen or dropped on the upload zone and uploads them to the field', function (): void {
    Livewire::test(MediaField::class, ['pageId' => $this->alphaId, 'field' => 'gallery'])
        ->assertSeeHtml('data-media-drop="uploads"')
        ->assertSeeHtml('x-data="lynxUploadZone"')
        ->assertSeeHtml('data-upload-multiple="true"');
});

it('keeps one file of those dropped on a field with one file', function (): void {
    Livewire::test(MediaField::class, ['pageId' => $this->alphaId, 'field' => 'cover'])
        ->assertSeeHtml('data-upload-multiple="false"');
});

it('scales images down in the browser to the field\'s maximum, at the site\'s quality', function (): void {
    config(['lynx-media.quality' => 75]);

    Livewire::test(MediaField::class, ['pageId' => $this->alphaId, 'field' => 'gallery'])
        ->assertSeeHtml('data-shrink-max-width="1600"')
        ->assertSeeHtml('data-shrink-max-height="1200"')
        ->assertSeeHtml('data-shrink-quality="75"');
});

it('scales images down to the default maximum when the field sets none', function (): void {
    Livewire::test(MediaField::class, ['pageId' => $this->alphaId, 'field' => 'cover'])
        ->assertSeeHtml('data-shrink-max-width="2560"')
        ->assertSeeHtml('data-shrink-max-height="2560"');
});

it('sends files of a files field as they are', function (): void {
    Livewire::test(MediaField::class, ['pageId' => $this->alphaId, 'field' => 'brochure'])
        ->assertSeeHtml('data-media-drop="uploads"')
        ->assertDontSeeHtml('data-shrink-max-width');
});
