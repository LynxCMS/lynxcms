<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Admin\MediaConverter;

/*
 * The page editor edits an images field as form items (MediaConverter::toForm) and saves them
 * back (fromForm): descriptions per language, tags typed as a comma list, the focus point, and a
 * picture switched off. Empty descriptions and tags are not stored.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images', settings: ['max_files' => 3]);
    lynx()->templates->update('course', ['add' => ['gallery']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
    $this->entry = lynx()->run(StoreMedia::class, ['page' => $this->alphaId, 'field' => 'gallery', 'base64' => base64_encode(jpeg(300, 200)), 'filename' => 'a.jpg'])->value;
    lynx()->run(SavePage::class, ['id' => $this->alphaId, 'values' => ['gallery' => [['values' => $this->entry]]]]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

/** @return array<string, mixed> the stored values of the gallery's first picture */
function storedPicture(): array
{
    return (array) (lynx()->pages->get(test()->alphaId)?->getUnformatted('gallery')[0]['values'] ?? []);
}

it('saves a picture\'s description, tags and focus from the page editor', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->set('values.gallery.default.0.description.pt', 'Uma vista')
        ->set('values.gallery.default.0.tags', ' hero, , detail ')
        ->set('values.gallery.default.0.focus', ['x' => 20, 'y' => 80, 'zoom' => 0])
        ->call('save')
        ->assertHasNoErrors();

    expect(storedPicture())->toMatchArray([
        'file' => $this->entry['file'],
        'description' => ['pt' => 'Uma vista'],
        'tags' => ['hero', 'detail'],
        'focus' => ['x' => 20, 'y' => 80, 'zoom' => 0],
    ]);
});

it('turns form items back into stored items, dropping what is empty', function (): void {
    $converter = app(MediaConverter::class);
    $items = $converter->toForm([['key' => 'k', 'enabled' => false, 'values' => $this->entry]]);
    $items[0]['tags'] = '';

    expect($converter->fromForm($items))->toBe([[
        'key' => 'k',
        'enabled' => false,
        'values' => array_filter([
            'file' => $this->entry['file'],
            'width' => 300,
            'height' => 200,
            'bytes' => $this->entry['bytes'],
            'mime' => 'image/jpeg',
            'focus' => ['x' => 50, 'y' => 50, 'zoom' => 0],
        ]),
    ]]);
});
