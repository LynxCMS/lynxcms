<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LynxCms\Media\Actions\StoreMedia;

/*
 * An images field stores JPEG, PNG, GIF, WebP and AVIF, read from the file's own header before
 * anything decodes it: ImageMagick picks a decoder from the content, and SVG, MVG or PostScript
 * named "x.png" would otherwise reach it. A picture whose name gives the wrong extension is
 * stored under its real one.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images');
    lynx()->templates->update('course', ['add' => ['gallery']]);
    $this->alphaId = (int) createCourses('alpha')['alpha']->id;
});

function storeUpload(string $contents, string $name): mixed
{
    return lynx()->run(StoreMedia::class, ['page' => test()->alphaId, 'field' => 'gallery', 'base64' => base64_encode($contents), 'filename' => $name])->value;
}

it('refuses content that is no raster image, whatever its name, with either driver', function (string $driver): void {
    config(['images.default' => $driver]);

    storeUpload('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>', 'x.png');
})->with(['gd', 'imagick'])->throws(ValidationException::class);

it('stores a picture under the extension of its real type', function (): void {
    ob_start();
    imagepng(imagecreatetruecolor(20, 10));
    $png = (string) ob_get_clean();

    expect(storeUpload($png, 'photo.jpg')['file'])->toEndWith('.png');
});

it('refuses a picture of a type the field does not store, whatever its name', function (): void {
    ob_start();
    imagebmp(imagecreatetruecolor(20, 10));
    $bmp = (string) ob_get_clean();

    storeUpload($bmp, 'photo.png');
})->throws(ValidationException::class);
