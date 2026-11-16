<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Media\Admin\Livewire\MediaField;

/*
 * The upload zone takes files dropped on it as well as chosen (issue #223): the drop hands them to
 * the same file input, so they upload and are checked the same way. A field with one file takes the
 * first file dropped.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images', settings: ['max_files' => 3]);
    lynx()->fields->add('cover', 'images', settings: ['max_files' => 1]);
    lynx()->templates->update('course', ['add' => ['gallery', 'cover']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('takes files dropped on the upload zone into its file input', function (): void {
    Livewire::test(MediaField::class, ['pageId' => $this->alphaId, 'field' => 'gallery'])
        ->assertSeeHtml('data-media-drop')
        ->assertSeeHtml('x-on:dragover.prevent')
        ->assertSeeHtml('x-on:drop.prevent="drop($event, true)"');
});

it('keeps one file of those dropped on a field with one file', function (): void {
    Livewire::test(MediaField::class, ['pageId' => $this->alphaId, 'field' => 'cover'])
        ->assertSeeHtml('x-on:drop.prevent="drop($event, false)"');
});
