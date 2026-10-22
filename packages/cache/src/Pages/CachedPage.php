<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

/**
 * A stored page: its HTML, when it was stored and when it expires (Unix times, null for never).
 * As a file, the times follow the document in a comment browsers ignore. Plain PHP, so the
 * pre-boot server can read files without the framework.
 *
 * @internal
 */
final readonly class CachedPage
{
    private const string MARKER = '<!-- lynx-cache ';

    /** Bytes read from the end of a file to find the marker. */
    private const int TAIL = 64;

    public function __construct(public string $html, public int $createdAt, public ?int $expiresAt) {}

    public static function fromFile(string $contents): ?self
    {
        $tail = substr($contents, -self::TAIL);
        $position = strrpos($tail, self::MARKER);

        if ($position === false) {
            return null;
        }

        $start = strlen($contents) - strlen($tail) + $position;
        [$created, $expires] = array_pad(explode(' ', substr($contents, $start + strlen(self::MARKER))), 2, '0');

        return new self(substr($contents, 0, max(0, $start - 1)), (int) $created, (int) $expires === 0 ? null : (int) $expires);
    }

    public function toFile(): string
    {
        return $this->html."\n".self::MARKER.$this->createdAt.' '.($this->expiresAt ?? 0)." -->\n";
    }

    public function isExpired(int $now): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= $now;
    }

    /**
     * The Cache-Control value stored pages are sent with, by PHP and by the web server rules.
     * Without an edge TTL browsers ask again on every visit, so a cleared page never lingers; web
     * servers would otherwise let browsers reuse the file for a while from its Last-Modified date.
     */
    public static function cacheControl(int $edgeTtl): string
    {
        return $edgeTtl > 0 ? 'public, max-age=0, s-maxage='.$edgeTtl : 'no-cache, private';
    }
}
