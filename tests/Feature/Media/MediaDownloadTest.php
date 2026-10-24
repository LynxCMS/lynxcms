<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Downloads\HostResolver;

/*
 * media.store downloads files only from public web servers: never the server's own network,
 * cloud metadata or a host that redirects there, and never more than the field allows.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('downloads', 'files', settings: ['max_kb' => 1]);
    lynx()->templates->update('course', ['add' => ['downloads']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;

    app()->instance(HostResolver::class, new class implements HostResolver
    {
        public function addresses(string $host): array
        {
            return [
                'cdn.example' => ['93.184.216.34'],
                'mirror.example' => ['2606:4700::1111'],
                'internal.example' => ['10.0.0.5'],
                'mixed.example' => ['93.184.216.34', '127.0.0.1'],
                'assets.internal' => ['10.0.0.9'],
            ][$host] ?? [];
        }
    });
});

/** @return array<string, mixed> */
function download(string $url): array
{
    return lynx()->run(StoreMedia::class, ['page' => test()->alphaId, 'field' => 'downloads', 'url' => $url])->value;
}

it('downloads a file from a public web server', function (): void {
    Http::fake(['cdn.example/*' => Http::response('Course notes')]);

    $entry = download('https://cdn.example/files/notes.txt');

    expect($entry['file'])->toMatch('/^'.test()->alphaId.'\/notes-[a-z0-9]{6}\.txt$/')
        ->and(Storage::disk('public')->get('lynx/'.$entry['file']))->toBe('Course notes');
});

it('refuses addresses on the server\'s own network', function (string $url): void {
    Http::fake();

    expect(fn (): array => download($url))->toThrow(ValidationException::class, 'is not on a public web server');
    Http::assertNothingSent();
})->with([
    'loopback' => 'http://127.0.0.1/notes.txt',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/notes.txt',
    'IPv6 loopback' => 'http://[::1]/notes.txt',
    'IPv4 in IPv6' => 'http://[::ffff:127.0.0.1]/notes.txt',
    'a name for a private address' => 'http://internal.example/notes.txt',
    'a name with one private address' => 'http://mixed.example/notes.txt',
    'a name that does not resolve' => 'http://nowhere.example/notes.txt',
    'another port' => 'http://cdn.example:6379/notes.txt',
]);

it('downloads only over http and https', function (): void {
    Http::fake();

    expect(fn (): array => download('ftp://cdn.example/notes.txt'))->toThrow(ValidationException::class, 'Only http and https URLs');
    Http::assertNothingSent();
});

it('checks every redirect before following it', function (): void {
    Http::fake([
        'cdn.example/moved.txt' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        'cdn.example/old.txt' => Http::response('', 301, ['Location' => '/new.txt']),
        'cdn.example/new.txt' => Http::response('Moved notes'),
        'mirror.example/*' => Http::response('', 302, ['Location' => 'https://mirror.example/again.txt']),
    ]);

    expect(fn (): array => download('https://cdn.example/moved.txt'))->toThrow(ValidationException::class, 'is not on a public web server');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '169.254.169.254'));

    expect(Storage::disk('public')->get('lynx/'.download('https://cdn.example/old.txt')['file']))->toBe('Moved notes')
        ->and(fn (): array => download('https://mirror.example/loop.txt'))->toThrow(ValidationException::class, 'redirects too many times');
});

it('stops reading at the field\'s size limit', function (): void {
    Http::fake([
        'cdn.example/announced.txt' => Http::response('small', 200, ['Content-Length' => '5000000']),
        'cdn.example/unannounced.txt' => Http::response(str_repeat('x', 2048)),
    ]);

    expect(fn (): array => download('https://cdn.example/announced.txt'))->toThrow(ValidationException::class, 'larger than this field allows')
        ->and(fn (): array => download('https://cdn.example/unannounced.txt'))->toThrow(ValidationException::class, 'larger than this field allows')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('lets the site trust hosts of its own, and turn downloads off', function (): void {
    Http::fake(['assets.internal/*' => Http::response('Internal notes'), 'cdn.example/*' => Http::response('Notes')]);
    config(['lynx-media.downloads.allowed_hosts' => ['assets.internal']]);

    expect(Storage::disk('public')->get('lynx/'.download('http://assets.internal/notes.txt')['file']))->toBe('Internal notes');

    config(['lynx-media.downloads.enabled' => false]);

    expect(fn (): array => download('https://cdn.example/notes.txt'))->toThrow(ValidationException::class, 'turned off on this site');
});
