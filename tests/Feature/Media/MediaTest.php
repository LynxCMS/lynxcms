<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Media\Actions\PruneMedia;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Admin\Livewire\MediaField;
use LynxCms\Media\Images\FocusCrop;
use LynxCms\Media\Values\MediaList;
use LynxCms\Media\Values\Pageimage;

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images', settings: ['max_files' => 3, 'max_width' => 1000, 'tags' => ['hero', 'detail']]);
    lynx()->fields->add('poster', 'images', settings: ['max_files' => 1, 'min_width' => 100]);
    lynx()->fields->add('downloads', 'files');
    lynx()->templates->update('course', ['add' => ['gallery', 'poster', 'downloads']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
});

/** @return array<string, mixed> */
function storeImage(string $field = 'gallery', int $width = 1600, int $height = 900, string $name = 'Set Photo.jpg'): array
{
    return lynx()->run(StoreMedia::class, ['page' => test()->alphaId, 'field' => $field, 'base64' => base64_encode(jpeg($width, $height)), 'filename' => $name])->value;
}

it('stores an upload in the page\'s folder and scales it to the field\'s maximum', function (): void {
    $entry = storeImage();

    expect($entry['file'])->toMatch('/^'.test()->alphaId.'\/set-photo-[a-z0-9]{6}\.jpg$/')
        ->and($entry)->toMatchArray(['width' => 1000, 'height' => 563, 'mime' => 'image/jpeg'])
        ->and(Storage::disk('public')->exists('lynx/'.$entry['file']))->toBeTrue();
});

it('refuses files the field does not accept', function (): void {
    expect(fn (): array => storeImage(name: 'notes.txt'))->toThrow(ValidationException::class, 'not allowed')
        ->and(fn (): array => storeImage('poster', 80, 80))->toThrow(ValidationException::class, 'smaller');
});

it('writes nothing on a dry run and removes the file when the action rolls back', function (): void {
    lynx()->run(StoreMedia::class, ['page' => test()->alphaId, 'field' => 'gallery', 'base64' => base64_encode(jpeg(10, 10)), 'filename' => 'a.jpg'], dryRun: true);
    expect(Storage::disk('public')->allFiles())->toBe([]);

    lynx()->hook(StoreMedia::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): array => storeImage())->toThrow(ActionHalted::class)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('keeps agents from storing server files', function (): void {
    lynx()->actingAs(Actor::mcp(), fn (): ActionResult => lynx()->run(StoreMedia::class, ['page' => test()->alphaId, 'field' => 'gallery', 'upload' => __FILE__]));
})->throws(ValidationException::class);

it('saves images with descriptions per language, tags and a focus point, and reads them back', function (): void {
    $entry = storeImage();
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['gallery' => [
        ['values' => ['description' => ['default' => 'On set', 'pt' => 'Na rodagem'], 'tags' => ['hero'], 'focus' => ['x' => 30, 'y' => 40]] + $entry],
    ]]]);
    $gallery = lynx()->pages->get('/courses/alpha/')?->gallery;

    expect($gallery)->toBeInstanceOf(MediaList::class)
        ->and($gallery->first())->toBeInstanceOf(Pageimage::class)
        ->and($gallery->first()?->description())->toBe('On set')
        ->and(lynx()->language('pt', fn () => lynx()->pages->get('/courses/alpha/')?->gallery->first()?->description()))->toBe('Na rodagem')
        ->and($gallery->first()?->focus())->toBe(['x' => 30, 'y' => 40, 'zoom' => 0])
        ->and($gallery->tagged('hero'))->toHaveCount(1)
        ->and($gallery->first()?->url())->toContain('/storage/lynx/'.test()->alphaId.'/');
});

it('refuses tags the field does not offer, and too many files', function (): void {
    $entry = storeImage();

    expect(fn (): ActionResult => lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['gallery' => [['values' => $entry + ['tags' => ['nope']]]]]]))->toThrow(ValidationException::class, 'not one of')
        ->and(fn (): ActionResult => lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['gallery' => array_fill(0, 4, ['values' => $entry])]]))->toThrow(ValidationException::class, 'At most 3');
});

it('reads a single-file field as one image', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['poster' => [['values' => storeImage('poster', 400, 600)]]]]);

    expect(lynx()->pages->get('/courses/alpha/')?->poster)->toBeInstanceOf(Pageimage::class);
});

it('makes sizes on first use with names that say how', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['gallery' => [['values' => ['focus' => ['x' => 30, 'y' => 40]] + storeImage()]]]]);
    $image = lynx()->pages->get('/courses/alpha/')?->gallery->first();

    $card = $image?->size(400, 400);
    $small = $image?->resize(500);
    $webp = $image?->webp();

    expect($card?->file())->toEndWith('.400x400-f30x40z0.jpg')
        ->and([$card?->width(), $card?->height()])->toBe([400, 400])
        ->and([$small?->width(), $small?->height()])->toBe([500, 282])
        ->and($image?->resize(5000)->width())->toBe(1000)
        ->and($webp?->file())->toEndWith('.1000x563.webp')
        ->and(Storage::disk('public')->exists('lynx/'.$card?->file()))->toBeTrue()
        ->and(getimagesizefromstring(Storage::disk('public')->get('lynx/'.$card?->file()))[0])->toBe(400)
        ->and($image?->srcset([200, 400]))->toContain(' 200w, ');
});

it('makes a chained size from the original, as one file named once', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['gallery' => [['values' => storeImage()]]]]);
    $image = lynx()->pages->get('/courses/alpha/')?->gallery->first();
    $folder = dirname('lynx/'.$image?->file());

    $thumb = $image?->size(300, 300, 75)->webp();
    $before = Storage::disk('public')->files($folder);
    $url = $thumb?->url();

    expect($thumb?->file())->toEndWith('.300x300-q75.webp')
        ->and($url)->toEndWith('.300x300-q75.webp')
        ->and([$thumb?->width(), $thumb?->height()])->toBe([300, 300])
        ->and($before)->toHaveCount(1)
        ->and(Storage::disk('public')->files($folder))->toHaveCount(2)
        ->and($image?->webp()->size(300, 300, 75)->file())->toBe($thumb?->file())
        ->and($image?->size(300, 300)->resize(100)->file())->toEndWith('.100x56.jpg');
});

it('crops around the focus point', function (): void {
    expect(FocusCrop::rect(1000, 500, 100, 100, 50, 50))->toBe([500, 500, 250, 0])
        ->and(FocusCrop::rect(1000, 500, 100, 100, 0, 50))->toBe([500, 500, 0, 0])
        ->and(FocusCrop::rect(1000, 500, 100, 100, 90, 50))->toBe([500, 500, 500, 0])
        ->and(FocusCrop::rect(1000, 500, 100, 100, 50, 50, 100))->toBe([250, 250, 375, 125]);
});

it('renders an img tag with lynx-image', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['gallery' => [['values' => storeImage() + ['description' => 'On set']]]]]);
    $html = Blade::render('<x-lynx-image :image="$image" :width="600" sizes="50vw" class="hero" />', ['image' => lynx()->pages->get('/courses/alpha/')?->gallery->first()]);

    expect($html)->toContain('width="600"')->toContain('alt="On set"')->toContain('srcset=')->toContain('class="hero"')->toContain('loading="lazy"')
        ->and(Blade::render('<x-lynx-image :image="null" />'))->toBe('');
});

it('keeps a draft\'s images in the page\'s folder', function (): void {
    $entry = storeImage();
    lynx()->pages->saveDraft('/courses/alpha/', ['gallery' => [['values' => $entry]]]);
    lynx()->pages->publishDraft('/courses/alpha/');

    expect(lynx()->pages->get('/courses/alpha/')?->gallery->first()?->file())->toBe($entry['file']);
});

it('prunes only files nothing uses', function (): void {
    $kept = storeImage();
    $orphan = storeImage();
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['gallery' => [['values' => $kept]]]]);
    lynx()->pages->get('/courses/alpha/')?->gallery->first()?->resize(200)->url();
    $revisionOnly = storeImage();
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['gallery' => [['values' => $kept], ['values' => $revisionOnly]]]]);
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['gallery' => [['values' => $kept]]]]);

    expect(lynx()->run(PruneMedia::class, ['older_than_hours' => 1])->value)->toBe(['deleted' => 0]);

    $result = lynx()->run(PruneMedia::class, ['older_than_hours' => 0])->value;

    expect($result)->toBe(['deleted' => 1])
        ->and(Storage::disk('public')->exists('lynx/'.$orphan['file']))->toBeFalse()
        ->and(Storage::disk('public')->exists('lynx/'.$kept['file']))->toBeTrue()
        ->and(Storage::disk('public')->exists('lynx/'.$revisionOnly['file']))->toBeTrue()
        ->and(count(Storage::disk('public')->allFiles('lynx/'.test()->alphaId)))->toBe(3);
});

it('deletes a page\'s folder with the page', function (): void {
    storeImage();
    lynx()->pages->delete('/courses/alpha/');

    expect(Storage::disk('public')->allFiles('lynx/'.test()->alphaId))->toBe([]);
});

it('stores files of other kinds', function (): void {
    $entry = lynx()->run(StoreMedia::class, ['page' => test()->alphaId, 'field' => 'downloads', 'base64' => base64_encode('%PDF-1.4 test'), 'filename' => 'Brochure.pdf'])->value;
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['downloads' => [['values' => $entry + ['description' => 'Brochure']]]]]);
    $file = lynx()->pages->get('/courses/alpha/')?->downloads->first();

    expect($file?->extension())->toBe('pdf')->and($file?->description())->toBe('Brochure')->and($file)->not->toBeInstanceOf(Pageimage::class);
});

it('uploads, sorts and removes files in the admin field', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $component = Livewire::test(MediaField::class, ['pageId' => test()->alphaId, 'field' => 'gallery'])
        ->set('uploads', [UploadedFile::fake()->createWithContent('a.jpg', jpeg(300, 200)), UploadedFile::fake()->createWithContent('b.jpg', jpeg(300, 200))]);

    $items = $component->get('items');
    expect($items)->toHaveCount(2)->and($items[0]['width'])->toBe(300);

    $component->call('sort', $items[1]['key'], 0)->call('focus', 0, 10, 90)->call('remove', 1);
    expect($component->get('items'))->toHaveCount(1)
        ->and($component->get('items')[0]['key'])->toBe($items[1]['key'])
        ->and($component->get('items')[0]['focus'])->toBe(['x' => 10, 'y' => 90, 'zoom' => 0]);

    $component->set('uploads', [UploadedFile::fake()->createWithContent('c.txt', 'x')])->assertHasErrors('uploads');
});

it('moves a file earlier or later with the arrow buttons', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $component = Livewire::test(MediaField::class, ['pageId' => test()->alphaId, 'field' => 'gallery'])
        ->set('uploads', [UploadedFile::fake()->createWithContent('a.jpg', jpeg(300, 200)), UploadedFile::fake()->createWithContent('b.jpg', jpeg(300, 200)), UploadedFile::fake()->createWithContent('c.jpg', jpeg(300, 200))]);
    [$a, $b, $c] = array_column($component->get('items'), 'key');

    $component->call('move', 0, 1);
    expect(array_column($component->get('items'), 'key'))->toBe([$b, $a, $c]);

    $component->call('move', 2, -1)->call('move', 0, -1)->call('move', 2, 1);
    expect(array_column($component->get('items'), 'key'))->toBe([$b, $c, $a]);
});

it('shows how to reorder files when there are several', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(MediaField::class, ['pageId' => test()->alphaId, 'field' => 'gallery'])
        ->set('uploads', [UploadedFile::fake()->createWithContent('a.jpg', jpeg(300, 200)), UploadedFile::fake()->createWithContent('b.jpg', jpeg(300, 200))])
        ->assertSee('Drag to reorder')
        ->assertSeeHtml('wire:click="move(0, 1)"');
});

it('shows media fields as an upload input in the page editor', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageEditor::class, ['pageId' => test()->alphaId])->assertSeeLivewire(MediaField::class);
    expect(DB::table('lynx_f_gallery')->count())->toBe(0);
});

it('nests images in matrix blocks, stores and keeps them', function (): void {
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [['slug' => 'still', 'fields' => [['name' => 'gallery'], ['name' => 'summary']]]]]);
    lynx()->templates->update('course', ['fields' => ['title', 'blocks']]);
    $entry = lynx()->run(StoreMedia::class, ['page' => test()->alphaId, 'field' => 'gallery', 'base64' => base64_encode(jpeg(400, 300)), 'filename' => 'still.jpg'])->value;
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['blocks' => [['type' => 'still', 'values' => ['gallery' => [['values' => $entry + ['description' => ['pt' => 'Rodagem']]]]]]]]]);

    $block = lynx()->pages->get('/courses/alpha/')?->blocks->first();
    expect($block?->gallery)->toBeInstanceOf(MediaList::class)
        ->and($block?->gallery->first()?->width())->toBe(400)
        ->and(lynx()->language('pt', fn () => lynx()->pages->get('/courses/alpha/')?->blocks->first()?->gallery->first()?->description()))->toBe('Rodagem');

    lynx()->run(PruneMedia::class, ['older_than_hours' => 0]);
    expect(Storage::disk('public')->exists('lynx/'.$entry['file']))->toBeTrue();
});

it('refuses item fields as subfields unless they nest', function (): void {
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'title']]]);

    expect(fn () => lynx()->fields->add('bad', 'matrix', settings: ['types' => [['slug' => 'x', 'fields' => [['name' => 'credits']]]]]))->toThrow(ValidationException::class);
});
