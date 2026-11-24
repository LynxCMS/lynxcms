<?php

declare(strict_types=1);

/*
 * Images scaled down in the browser before uploading (image-shrink.js, used by the media upload
 * zone): a photo larger than the field keeps is drawn at the field's maximum and encoded in its own
 * type; a smaller one, and other types, are sent as they are. Run in Chromium, as canvas and
 * createImageBitmap behave there. The zone's wiring is tested in tests/Feature/Media/MediaDropTest.
 */

beforeEach(function (): void {
    signInSite();
    $this->actingAs(browserUser('ana@example.com'));
});

/** Runs lynxShrinkImage on a generated image and reports the file it returns. */
function shrinkInBrowser(string $type, int $width, int $height): mixed
{
    return visit('/lynx')->script(<<<JS
        (async () => {
            const canvas = document.createElement('canvas');
            canvas.width = {$width};
            canvas.height = {$height};
            const context = canvas.getContext('2d');
            const pixels = context.createImageData({$width}, {$height});
            for (let i = 0; i < pixels.data.length; i++) pixels.data[i] = i % 4 === 3 ? 255 : Math.random() * 255;
            context.putImageData(pixels, 0, 0);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, '{$type}', 0.92));
            const file = new File([blob], 'photo', { type: '{$type}' });
            const shrunk = await window.lynxShrinkImage(file, { maxWidth: 400, maxHeight: 300, quality: 80 });
            const bitmap = await createImageBitmap(shrunk);
            return { same: shrunk === file, type: shrunk.type, width: bitmap.width, height: bitmap.height, smaller: shrunk.size < file.size };
        })()
        JS);
}

it('scales a large JPEG down to the field\'s maximum, keeping its aspect ratio and type', function (): void {
    expect(shrinkInBrowser('image/jpeg', 1600, 1200))->toBe(['same' => false, 'type' => 'image/jpeg', 'width' => 400, 'height' => 300, 'smaller' => true]);
});

it('scales a tall PNG down to fit the height', function (): void {
    expect(shrinkInBrowser('image/png', 600, 1200))->toMatchArray(['same' => false, 'type' => 'image/png', 'width' => 150, 'height' => 300]);
});

it('sends an image within the maximum as it is', function (): void {
    expect(shrinkInBrowser('image/jpeg', 300, 200))->toMatchArray(['same' => true, 'width' => 300, 'height' => 200]);
});
