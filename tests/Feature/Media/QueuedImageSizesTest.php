<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use LynxCms\Contracts\Pages\PageCache;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Doctor\QueuedSizes;
use LynxCms\Media\Images\MakeImageSize;
use LynxCms\Media\Values\Pageimage;

/*
 * Image sizes in a queue (documentation/plans/image-sizes.md): with lynx-media.queue_sizes on,
 * printing a size that does not exist yet queues a job to make it and prints the nearest image
 * that exists; the page is kept out of the page cache until the sizes are made. Off by default,
 * when sizes are made during the render as before.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images', settings: ['max_files' => 3]);
    lynx()->templates->update('course', ['add' => ['gallery']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
    $stored = lynx()->run(StoreMedia::class, ['page' => $this->alphaId, 'field' => 'gallery', 'base64' => base64_encode(jpeg(1600, 900)), 'filename' => 'photo.jpg'])->value;
    lynx()->run(SavePage::class, ['id' => $this->alphaId, 'values' => ['gallery' => [['values' => ['focus' => ['x' => 30, 'y' => 40, 'zoom' => 0]] + $stored]]]]);
});

function queuedImage(): Pageimage
{
    $image = lynx()->pages->get('/courses/alpha/')?->gallery->first();
    assert($image instanceof Pageimage);

    return $image;
}

/** Files in the page's folder: the original and the sizes made. Reading a size's file() would make it. */
function filesMade(): int
{
    return count(Storage::disk('public')->files('lynx/'.test()->alphaId));
}

it('makes a size during the render when the setting is off, as before', function (): void {
    Queue::fake();
    $small = queuedImage()->resize(400);

    expect($small->url())->toEndWith('.400x225.jpg')
        ->and(filesMade())->toBe(2);
    Queue::assertNothingPushed();
});

it('queues a missing size and prints the original meanwhile', function (): void {
    config(['lynx-media.queue_sizes' => true]);
    Queue::fake();
    $image = queuedImage();

    $url = $image->resize(400)->url();

    expect($url)->toBe($image->url())
        ->and(filesMade())->toBe(1);
    Queue::assertPushed(MakeImageSize::class, fn (MakeImageSize $job): bool => str_ends_with($job->file, '.400x225.jpg'));
});

it('prints the smallest larger size that exists while a size waits', function (): void {
    $image = queuedImage();
    $image->resize(800)->url();
    $image->resize(1200)->url();
    config(['lynx-media.queue_sizes' => true]);
    Queue::fake();

    expect($image->resize(400)->url())->toBe($image->resize(800)->url());
});

it('prints a size that exists without queuing it', function (): void {
    $image = queuedImage();
    $made = $image->resize(400)->url();
    config(['lynx-media.queue_sizes' => true]);
    Queue::fake();

    expect($image->resize(400)->url())->toBe($made);
    Queue::assertNothingPushed();
});

it('makes the size in the job, so the next render prints it', function (): void {
    config(['lynx-media.queue_sizes' => true]);
    Queue::fake();
    $small = queuedImage()->resize(400);
    $small->url();
    Queue::assertPushed(MakeImageSize::class, function (MakeImageSize $job): bool {
        app()->call($job->handle(...));

        return true;
    });

    expect(filesMade())->toBe(2)
        ->and(queuedImage()->resize(400)->url())->toEndWith('.400x225.jpg');
});

it('queues each missing size once, however often it is printed', function (): void {
    config(['lynx-media.queue_sizes' => true]);
    Queue::fake();

    queuedImage()->resize(400)->url();
    queuedImage()->resize(400)->url();

    expect(new MakeImageSize('k', [], 'lynx/1/photo.400x225.jpg', []))->toBeInstanceOf(ShouldBeUnique::class)
        ->and(new MakeImageSize('k', [], '1/photo.400x225.jpg', [])->uniqueId())->toBe('1/photo.400x225.jpg');
});

it('logs a size it cannot make, and does not retry', function (): void {
    Log::spy();
    $image = queuedImage();
    Storage::disk('public')->delete('lynx/'.$image->file());
    $job = new MakeImageSize($image->key(), ['file' => $image->file(), 'width' => 1600, 'height' => 900], dirname($image->file()).'/photo.400x225.jpg', ['width' => 400, 'height' => 0, 'crop' => false, 'quality' => null, 'format' => null]);

    app()->call($job->handle(...));

    expect($job->tries)->toBe(1);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'image size') && $context['file'] === dirname($image->file()).'/photo.400x225.jpg');
});

it('makes the file at once for code that reads it', function (): void {
    config(['lynx-media.queue_sizes' => true]);
    Queue::fake();
    $small = queuedImage()->resize(400);

    expect(filesMade())->toBe(1)
        ->and($small->file())->toEndWith('.400x225.jpg')
        ->and(filesMade())->toBe(2);
    Queue::assertNothingPushed();
});

it('keeps a page that printed a waiting size out of the page cache', function (): void {
    config(['lynx-media.queue_sizes' => true]);
    Queue::fake();

    queuedImage()->resize(800)->url();

    expect(request()->attributes->get(PageCache::SKIP))->toBeTrue();
});

it('crops the waiting image in the browser at the focus point', function (): void {
    config(['lynx-media.queue_sizes' => true]);
    Queue::fake();

    $html = Blade::render('<x-lynx-image :image="$image" :width="300" :height="300" />', ['image' => queuedImage()]);

    expect($html)->toContain('width="300"')->toContain('height="300"')
        ->toContain('object-fit: cover')->toContain('object-position: 30% 40%');
});

it('warns when sizes are queued on the sync connection', function (): void {
    config(['lynx-media.queue_sizes' => true, 'queue.default' => 'sync']);

    expect(app(QueuedSizes::class)->run())->toHaveCount(1)
        ->and(app(QueuedSizes::class)->run()[0]->severity)->toBe(Severity::Warning);

    config(['queue.default' => 'database']);

    expect(app(QueuedSizes::class)->run())->toBe([]);
});
