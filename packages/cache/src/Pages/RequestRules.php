<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

/**
 * Which requests may be answered from the cache, and from which address. Plain PHP with no
 * framework calls, so the pre-boot server and the middleware apply the very same rules.
 *
 * @internal
 */
final readonly class RequestRules
{
    private const array METHODS = ['GET', 'HEAD'];

    /**
     * @param  list<string>  $hosts  lower case host names
     * @param  list<string>  $bypassCookies
     * @param  list<string>  $ignoredParameters  names, a trailing * matches a prefix
     */
    public function __construct(private array $hosts, private array $bypassCookies, private array $ignoredParameters) {}

    /**
     * @param  array<string, mixed>  $cookies
     */
    public function addressFor(string $method, string $host, string $path, string $query, array $cookies): ?CacheAddress
    {
        $host = strtolower($host);

        if (! in_array(strtoupper($method), self::METHODS, true) || ! in_array($host, $this->hosts, true) || $this->bypasses($cookies)) {
            return null;
        }

        $path = $this->safePath($path);

        return $path === null ? null : new CacheAddress($host, $path, $this->normalizedQuery($query));
    }

    /** @param array<string, mixed> $cookies */
    private function bypasses(array $cookies): bool
    {
        return array_intersect($this->bypassCookies, array_keys($cookies)) !== [];
    }

    /** The decoded, lower case path without outer slashes, or null when it could escape the folder. */
    private function safePath(string $path): ?string
    {
        $path = trim(mb_strtolower(rawurldecode($path)), '/');

        if ($path === '') {
            return '';
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/[\x00-\x1f\\\\]/', $segment) === 1) {
                return null;
            }
        }

        return $path;
    }

    /** Ignored parameters dropped, the rest sorted and encoded the same way every time. */
    private function normalizedQuery(string $query): string
    {
        $pairs = [];

        foreach (explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = rawurldecode(str_replace('+', ' ', $name));

            if ($name !== '' && ! $this->isIgnored($name)) {
                $pairs[] = rawurlencode($name).'='.rawurlencode(rawurldecode(str_replace('+', ' ', $value)));
            }
        }

        sort($pairs);

        return implode('&', $pairs);
    }

    private function isIgnored(string $name): bool
    {
        foreach ($this->ignoredParameters as $ignored) {
            $matches = str_ends_with($ignored, '*') ? str_starts_with($name, substr($ignored, 0, -1)) : $name === $ignored;

            if ($matches) {
                return true;
            }
        }

        return false;
    }
}
