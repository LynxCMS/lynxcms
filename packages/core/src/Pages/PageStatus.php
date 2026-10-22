<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

/**
 * Page status flags, stored as a bitmask in lynx_pages.status.
 *
 * @api
 */
final class PageStatus
{
    public const int HIDDEN = 1;

    public const int UNPUBLISHED = 2;

    public const int LOCKED = 4;

    public const int UNIQUE = 8;

    public const int SYSTEM = 16;

    /** @var array<string, int> */
    public const array NAMES = [
        'hidden' => self::HIDDEN,
        'unpublished' => self::UNPUBLISHED,
        'locked' => self::LOCKED,
        'unique' => self::UNIQUE,
        'system' => self::SYSTEM,
    ];

    /**
     * @param  list<string>  $names
     */
    public static function fromNames(array $names): int
    {
        $status = 0;

        foreach ($names as $name) {
            $status |= self::NAMES[$name] ?? 0;
        }

        return $status;
    }

    /**
     * @return list<string>
     */
    public static function toNames(int $status): array
    {
        return array_keys(array_filter(self::NAMES, fn (int $bit): bool => ($status & $bit) === $bit));
    }
}
