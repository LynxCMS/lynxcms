<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Pages\Page;
use LynxCms\Media\Downloads\HostResolver;
use LynxCms\Media\Remote\FetchRemoteMedia;
use LynxCms\Media\Remote\RemoteMedia;

/*
 * On a developer's machine, media files the content came without are downloaded from
 * production when something needs them (ADR-0082): a browser asking for the file, or a template
 * asking for an image size. lynx:media:pull fetches them ahead in queued jobs.
 */

const PRODUCTION_MEDIA = 'https://www.example.com/storage/lynx';

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images');
    lynx()->fields->add('downloads', 'files');
    lynx()->templates->update('course', ['add' => ['gallery', 'downloads']]);
    ['alpha' => $alpha] = createCourses('alpha');
    // Values as an import without files leaves them: production's stored names.
    $alpha->gallery = [['values' => ['file' => '99/photo.jpg', 'bytes' => 10, 'mime' => 'image/jpeg', 'width' => 400, 'height' => 300]]];
    $alpha->downloads = [['values' => ['file' => '99/notes.pdf', 'bytes' => 12, 'mime' => 'application/pdf']]];
    $alpha->save();
    $this->alphaId = (int) $alpha->id;

    config(['lynx-media.remote.url' => PRODUCTION_MEDIA]);
    app()->instance(HostResolver::class, new class implements HostResolver
    {
        public function addresses(string $host): array
        {
            return $host === 'www.example.com' ? ['93.184.216.34'] : [];
        }
    });
    Http::fake([
        PRODUCTION_MEDIA.'/99/photo.jpg' => Http::response(jpeg(400, 300)),
        PRODUCTION_MEDIA.'/99/notes.pdf' => Http::response('%PDF-1.4 notes'),
        '*' => Http::response('', 404),
    ]);
});

function remoteMediaCourse(): Page
{
    return lynx()->pages->get(test()->alphaId);
}

it('downloads a missing file from production when the browser asks for it, once', function (): void {
    $this->get('/storage/lynx/99/notes.pdf')->assertOk();
    $this->get('/storage/lynx/99/notes.pdf')->assertOk();

    expect(Storage::disk('public')->get('lynx/99/notes.pdf'))->toBe('%PDF-1.4 notes');
    Http::assertSentCount(1);
});

it('makes image sizes from an original it downloads first', function (): void {
    $size = remoteMediaCourse()->gallery->first()?->size(100, 100);
    $size?->url();

    Storage::disk('public')->assertExists('lynx/99/photo.jpg');
    Storage::disk('public')->assertExists('lynx/'.$size?->file());
});

it('answers 404 for files production doesn\'t have', function (): void {
    $this->get('/storage/lynx/99/missing.pdf')->assertNotFound();
});

it('never downloads in production, nor from a remote that is not https', function (string $setting): void {
    $setting === 'production' ? app()->detectEnvironment(fn (): string => 'production') : config(['lynx-media.remote.url' => 'http://www.example.com/storage/lynx']);

    expect(app(RemoteMedia::class)->ensure('99/notes.pdf'))->toBeFalse();
    Storage::disk('public')->assertMissing('lynx/99/notes.pdf');
    Http::assertNothingSent();
})->with(['production', 'http']);

it('refuses names that are not stored media files', function (string $file): void {
    expect(app(RemoteMedia::class)->ensure($file))->toBeFalse();
    Http::assertNothingSent();
})->with(['../.env', '99/../../secret', '99/.htaccess', 'photo.jpg']);

it('queues a download for every missing file with lynx:media:pull', function (): void {
    Queue::fake();
    Storage::disk('public')->put('lynx/99/notes.pdf', 'already here');

    $this->artisan('lynx:media:pull')->expectsOutputToContain('1 file')->assertSuccessful();

    Queue::assertPushed(FetchRemoteMedia::class, 1);
    Queue::assertPushed(FetchRemoteMedia::class, fn (FetchRemoteMedia $job): bool => $job->file === '99/photo.jpg');
});

it('downloads the file a queued job names', function (): void {
    new FetchRemoteMedia('99/notes.pdf')->handle(app(RemoteMedia::class));

    Storage::disk('public')->assertExists('lynx/99/notes.pdf');
});

it('exports a content package without its files', function (): void {
    $folder = testingPath('remote-media-export');
    File::deleteDirectory($folder);
    Storage::disk('public')->put('lynx/99/notes.pdf', '%PDF-1.4 notes');

    $this->artisan('lynx:export', ['path' => $folder, '--without-files' => true])->assertSuccessful();

    expect(File::isDirectory($folder.'/files'))->toBeFalse()
        ->and(File::get($folder.'/pages.jsonl'))->toContain('99/notes.pdf');
    File::deleteDirectory($folder);
});
