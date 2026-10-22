<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Pages as HTML files under one folder, laid out as {host}/{path}/index.html so nginx, Apache or
 * Caddy can send them without PHP. The times are kept in a comment after the document
 * (CachedPage::toFile()).
 *
 * @internal
 */
final readonly class FilePageStore implements PageStore
{
    public function __construct(private Filesystem $files, private Generation $generation, private string $root) {}

    public function get(CacheAddress $address): ?CachedPage
    {
        $file = $this->pathOf($address);
        $contents = is_file($file) ? $this->files->get($file) : null;
        $page = $contents === null ? null : CachedPage::fromFile($contents);

        return $page === null || $page->isExpired(now()->getTimestamp()) ? null : $page;
    }

    public function put(CacheAddress $address, CachedPage $page): void
    {
        $file = $this->pathOf($address);
        $this->files->ensureDirectoryExists(dirname($file));
        $temporary = $file.'.'.Str::random(8).'.tmp';
        $this->files->put($temporary, $page->toFile());
        // A rename is atomic, so a web server never sends half a page.
        $this->files->move($temporary, $file);
    }

    public function forget(CacheAddress $address): void
    {
        $this->files->delete($this->pathOf($address));
    }

    public function flush(): void
    {
        $this->generation->bump();

        if (! is_dir($this->root)) {
            return;
        }

        // Renaming first empties the folder at once; deleting a large tree takes a while.
        $trash = $this->root.'.trash-'.Str::random(8);
        $this->files->moveDirectory($this->root, $trash);
        $this->files->deleteDirectory($trash);
    }

    private function pathOf(CacheAddress $address): string
    {
        return $this->root.'/'.$address->file();
    }
}
