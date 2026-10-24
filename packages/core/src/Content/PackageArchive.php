<?php

declare(strict_types=1);

namespace LynxCms\Core\Content;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Zips a content package directory and unpacks one (ADR-0040), for backups and moving content.
 *
 * @internal
 */
final class PackageArchive
{
    public static function zip(string $directory, string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot write {$zipPath}.");
        }

        foreach (File::allFiles($directory) as $file) {
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $file->getRelativePathname()));
        }

        $zip->close();
    }

    /** @return string the directory the package was unpacked into; delete it when done */
    public static function unzip(string $zipPath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Cannot read {$zipPath}.");
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (! self::isSafeEntry($name)) {
                $zip->close();

                throw new RuntimeException("{$zipPath} holds a file outside the package: [{$name}].");
            }
        }

        $directory = sys_get_temp_dir().'/lynx-import-'.bin2hex(random_bytes(6));
        $zip->extractTo($directory);
        $zip->close();

        return $directory;
    }

    /** A relative path inside the archive: no absolute path, drive letter or ".." segment. */
    private static function isSafeEntry(string $name): bool
    {
        $path = str_replace('\\', '/', $name);

        return $path !== '' && ! str_starts_with($path, '/') && preg_match('/^[A-Za-z]:/', $path) !== 1
            && ! in_array('..', explode('/', $path), true);
    }
}
