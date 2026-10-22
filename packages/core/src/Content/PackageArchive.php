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

        $directory = sys_get_temp_dir().'/lynx-import-'.bin2hex(random_bytes(6));
        $zip->extractTo($directory);
        $zip->close();

        return $directory;
    }
}
