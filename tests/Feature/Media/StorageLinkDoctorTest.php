<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/*
 * The media package's lynx:doctor check: the public media disk needs its storage:link.
 */

beforeEach(function (): void {
    courseSite();
    $this->link = testingPath('doctor-link').'/storage';
    File::deleteDirectory(dirname($this->link));
    config(['filesystems.links' => [$this->link => config('filesystems.disks.public.root')]]);
});

afterEach(function (): void {
    File::deleteDirectory(dirname($this->link));
});

/** @return list<array{severity: string, message: string, fix: string|null}> */
function storageLinkFindings(int $exit): array
{
    expect(Artisan::call('lynx:doctor', ['--json' => true]))->toBe($exit);

    return collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->firstWhere('key', 'media-storage-link')['findings'];
}

it('fails while the public media disk has no storage link', function (): void {
    expect(array_column(storageLinkFindings(exit: 1), 'severity'))->toBe(['error']);
});

it('passes once the link exists', function (): void {
    File::ensureDirectoryExists($this->link);

    expect(storageLinkFindings(exit: 0))->toBe([]);
});
