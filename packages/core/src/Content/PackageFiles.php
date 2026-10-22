<?php

declare(strict_types=1);

namespace LynxCms\Core\Content;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Fields\CarriesFiles;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Pages\Page;

/**
 * The files of a content package (ADR-0050): export copies each file a page's values name into
 * files/{stored name}; import stores them again for the page they land on and rewrites the values.
 * Without a PageFiles binding (no media package) packages carry no files.
 *
 * @internal
 */
final readonly class PackageFiles
{
    public const string DIRECTORY = 'files';

    public function __construct(private FieldRegistry $fields, private Container $container) {}

    public function export(Page $page, string $directory): void
    {
        $store = $this->store();

        if (! $store instanceof PageFiles) {
            return;
        }

        foreach ($page->valuesByLanguage() as $values) {
            foreach ($values as $name => $value) {
                foreach ($this->filesOf((string) $name, $value) as $file) {
                    $this->copyOut($store, $file, $directory);
                }
            }
        }
    }

    /**
     * The values with each packaged file stored for the page.
     *
     * @param  array<string, mixed>  $values  field => value
     * @return array<string, mixed>
     */
    public function import(array $values, string $directory, int $pageId, bool $dryRun): array
    {
        $store = $this->store();

        if (! $store instanceof PageFiles) {
            return $values;
        }

        $storeFile = function (string $file) use ($store, $directory, $pageId, $dryRun): string {
            $source = $directory.'/'.self::DIRECTORY.'/'.$file;

            return $this->isSafe($file) && is_file($source) ? $store->write($pageId, $file, File::get($source), $dryRun) : $file;
        };

        foreach ($values as $name => $value) {
            $values[$name] = $this->mapFiles($name, $value, $storeFile);
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function filesOf(string $name, mixed $value): array
    {
        $field = $this->fields->find($name);
        $type = $field?->fieldType();

        return $field !== null && $type instanceof CarriesFiles ? $type->files($value, $field->config()) : [];
    }

    /**
     * @param  Closure(string): string  $map
     */
    private function mapFiles(string $name, mixed $value, Closure $map): mixed
    {
        $field = $this->fields->find($name);
        $type = $field?->fieldType();

        return $field !== null && $type instanceof CarriesFiles ? $type->mapFiles($value, $map, $field->config()) : $value;
    }

    private function copyOut(PageFiles $store, string $file, string $directory): void
    {
        $target = $directory.'/'.self::DIRECTORY.'/'.$file;

        if (! $this->isSafe($file) || is_file($target)) {
            return;
        }

        $contents = $store->read($file);

        if ($contents !== null) {
            File::ensureDirectoryExists(dirname($target));
            File::put($target, $contents);
        }
    }

    /** Stored names are relative paths of plain segments; a package may come from anywhere. */
    private function isSafe(string $file): bool
    {
        return ! str_contains($file, '..') && preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$#', $file) === 1;
    }

    private function store(): ?PageFiles
    {
        return $this->container->bound(PageFiles::class) ? $this->container->make(PageFiles::class) : null;
    }
}
