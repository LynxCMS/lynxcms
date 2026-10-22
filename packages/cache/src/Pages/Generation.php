<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use LynxCms\Core\Support\Value;

/**
 * Two values in the cache store. The generation changes when every page is dropped; the shared
 * store driver puts it in its keys, so every server sees a flush at once. The revision changes
 * on any clear: a page notes it before rendering and is stored only if it is unchanged after,
 * so a page rendered from content that changed meanwhile is not kept.
 *
 * @internal
 */
final readonly class Generation
{
    private const string GENERATION = 'lynx-cache:generation';

    private const string REVISION = 'lynx-cache:revision';

    public function __construct(private Repository $cache) {}

    public function current(): string
    {
        return $this->read(self::GENERATION);
    }

    public function revision(): string
    {
        return $this->read(self::REVISION);
    }

    /** After every page was dropped. */
    public function bump(): string
    {
        $this->touch();

        return $this->write(self::GENERATION);
    }

    /** After some pages were dropped. */
    public function touch(): void
    {
        $this->write(self::REVISION);
    }

    private function read(string $key): string
    {
        $value = Value::string($this->cache->get($key));

        return $value === '' ? $this->write($key) : $value;
    }

    private function write(string $key): string
    {
        $value = Str::random(12);
        $this->cache->forever($key, $value);

        return $value;
    }
}
