<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Core\Content\PackageArchive;

/*
 * A content package's zip may only hold files inside the package: an entry that climbs out of
 * the folder, or names an absolute path, stops the import before anything is extracted.
 */

function zipWith(string $entry): string
{
    $path = testingPath('archive').'/package.zip';
    File::ensureDirectoryExists(dirname($path));
    File::delete($path);
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('pages.jsonl', '');
    $zip->addFromString($entry, 'x');
    $zip->close();

    return $path;
}

afterEach(fn () => File::deleteDirectory(testingPath('archive')));

it('extracts a package that keeps its files inside', function (): void {
    $directory = PackageArchive::unzip(zipWith('files/12/photo.jpg'));

    expect(File::exists($directory.'/files/12/photo.jpg'))->toBeTrue();
    File::deleteDirectory($directory);
});

it('refuses entries outside the package', function (string $entry): void {
    expect(fn (): string => PackageArchive::unzip(zipWith($entry)))->toThrow(RuntimeException::class, 'holds a file outside the package');
})->with(['../evil.php', 'files/../../evil.php', '/etc/cron.d/evil', 'C:/evil.php', '..\\evil.php']);
