<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Media\Actions\StoreMedia;

/*
 * A media value names a file media.store kept for the page. When an editor saves one, the file
 * must be in the page's own folder, stored, of a type the field allows, and for an images field a
 * picture; its size, type and dimensions come from the file, whatever the value says. Superusers
 * and the site's code are trusted, as imports keep the names the source site gave.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images');
    lynx()->fields->add('downloads', 'files', settings: ['extensions' => 'pdf txt']);
    lynx()->templates->update('course', ['add' => ['gallery', 'downloads']]);
    ['alpha' => $alpha, 'beta' => $beta] = createCourses('alpha', 'beta');
    $this->alphaId = (int) $alpha->id;
    $this->betaId = (int) $beta->id;
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'pages.view'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['editor']]);
});

/** @return array<string, mixed> the entry media.store returns */
function storedFor(int $page, string $field, string $contents, string $name): array
{
    return lynx()->run(StoreMedia::class, ['page' => $page, 'field' => $field, 'base64' => base64_encode($contents), 'filename' => $name])->value;
}

/** @param  array<string, mixed>  $entry */
function saveAsEditor(int $page, string $field, array $entry): mixed
{
    return app(ActionRunner::class)->actingAs(Actor::user(5), fn (): ActionResult => lynx()->run(SavePage::class, ['id' => $page, 'values' => [$field => [['values' => $entry]]]]));
}

it('keeps an editor\'s own file, with the size and dimensions read from it', function (): void {
    $entry = storedFor($this->alphaId, 'gallery', jpeg(400, 300), 'photo.jpg');

    saveAsEditor($this->alphaId, 'gallery', ['width' => 90000, 'height' => 90000, 'bytes' => 1] + $entry);

    $stored = lynx()->pages->get($this->alphaId)->rawValuesOf('gallery');
    $values = array_first($stored)[0]['values'];

    expect([$values['width'], $values['height'], $values['mime']])->toBe([400, 300, 'image/jpeg'])
        ->and($values['bytes'])->toBe($entry['bytes']);
});

it('refuses a value an editor sends that names another page\'s file', function (): void {
    $entry = storedFor($this->betaId, 'gallery', jpeg(40, 30), 'photo.jpg');

    saveAsEditor($this->alphaId, 'gallery', $entry);
})->throws(ValidationException::class);

it('refuses a file of a type the field does not allow, stored through another field', function (): void {
    $entry = storedFor($this->alphaId, 'downloads', str_repeat('x', 100), 'bomb.txt');

    saveAsEditor($this->alphaId, 'gallery', $entry);
})->throws(ValidationException::class);

it('refuses a file that is not stored', function (): void {
    saveAsEditor($this->alphaId, 'gallery', ['file' => $this->alphaId.'/ghost-abc123.jpg', 'bytes' => 10, 'mime' => 'image/jpeg', 'width' => 4, 'height' => 4]);
})->throws(ValidationException::class);

it('refuses a file that is not a picture in an images field', function (): void {
    Storage::disk('public')->put('lynx/'.$this->alphaId.'/fake-abc123.jpg', 'not a picture');

    saveAsEditor($this->alphaId, 'gallery', ['file' => $this->alphaId.'/fake-abc123.jpg', 'bytes' => 10, 'mime' => 'image/jpeg', 'width' => 4, 'height' => 4]);
})->throws(ValidationException::class);

it('refuses a stored name that ends in a newline', function (): void {
    $entry = storedFor($this->alphaId, 'gallery', jpeg(40, 30), 'photo.jpg');

    saveAsEditor($this->alphaId, 'gallery', ['file' => $entry['file']."\n"] + $entry);
})->throws(ValidationException::class);

it('lets an editor save and publish a draft naming the page\'s own files', function (): void {
    $entry = storedFor($this->alphaId, 'gallery', jpeg(40, 30), 'photo.jpg');

    app(ActionRunner::class)->actingAs(Actor::user(5), function () use ($entry): void {
        lynx()->pages->saveDraft('/courses/alpha/', ['gallery' => [['values' => $entry]]]);
        lynx()->pages->publishDraft('/courses/alpha/');
    });

    expect(lynx()->pages->get('/courses/alpha/')?->gallery->first()?->file())->toBe($entry['file']);
});

it('lets an editor duplicate a page with files, which the copy gets in its own folder', function (): void {
    $entry = storedFor($this->alphaId, 'gallery', jpeg(40, 30), 'photo.jpg');
    lynx()->run(SavePage::class, ['id' => $this->alphaId, 'values' => ['gallery' => [['values' => $entry]]]]);
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'pages.view'], ['permission' => 'pages.edit'], ['permission' => 'pages.create']]]);

    $copy = app(ActionRunner::class)->actingAs(Actor::user(5), fn (): mixed => lynx()->pages->duplicate('/courses/alpha/'));

    expect(lynx()->pages->get((int) $copy->id)?->gallery->first()?->file())->toStartWith($copy->id.'/');
});
