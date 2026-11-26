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
    /** What a flushed folder is renamed to, beside the folder, before it is deleted. */
    public const string TRASH = '.trash-';

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

        // Renaming first empties the folder at once; deleting a large tree takes a while.
        if (is_dir($this->root)) {
            $this->files->moveDirectory($this->root, $this->root.self::TRASH.Str::random(8));
        }

        $this->emptyTrash();
    }

    /**
     * Deletes this flush's folder and any an earlier one left: a page written into the folder
     * while it was being deleted can make the delete fail, and the folder then stays in public/.
     */
    private function emptyTrash(): void
    {
        foreach (glob($this->root.self::TRASH.'*', GLOB_ONLYDIR) ?: [] as $trash) {
            $this->files->deleteDirectory($trash);
        }
    }

    private function pathOf(CacheAddress $address): string
    {
        return $this->root.'/'.$address->file();
    }
}
