<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

use Illuminate\Translation\FileLoader;

/**
 * Laravel's FileLoader, widened to regional locales as RegionalLoader describes. It stays a
 * FileLoader, so code that checks the translator's loader for one keeps working.
 *
 * @internal
 */
final class RegionalFileLoader extends FileLoader
{
    /** The same files, paths, JSON paths and namespaces as $loader. */
    public static function from(FileLoader $loader): self
    {
        $regional = new self($loader->files, $loader->paths);
        $regional->jsonPaths = $loader->jsonPaths;
        $regional->hints = $loader->hints;

        return $regional;
    }

    /** @return array<array-key, mixed> */
    public function load($locale, $group, $namespace = null): array
    {
        return RegionalLoader::merge(fn (string $candidate): array => parent::load($candidate, $group, $namespace), $locale);
    }
}
