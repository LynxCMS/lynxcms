<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

/**
 * Where a page is stored: a host, a path without its outer slashes and a sorted query. The
 * same address names the file of the "files" driver and the key of the "store" driver.
 *
 * @internal
 */
final readonly class CacheAddress
{
    public function __construct(public string $host, public string $path, public string $query = '') {}

    public function key(): string
    {
        return $this->host.'/'.$this->path.($this->query === '' ? '' : '?'.$this->query);
    }

    /** The file below the cache folder; web servers find it from the host and the URL path. */
    public function file(): string
    {
        $folder = $this->path === '' ? $this->host : $this->host.'/'.$this->path;

        return $folder.'/'.($this->query === '' ? 'index.html' : 'q-'.sha1($this->query).'.html');
    }
}
