<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use LynxCms\Core\Support\Value;

/**
 * The path the admin is served at (ADR-0122): "lynx" unless LYNX_ADMIN_PREFIX says otherwise,
 * e.g. "admin" on a site that needs /lynx for a page, or a random path that is hard to guess.
 * Every admin route, the sign-in and Fortify's redirects are built from it.
 *
 * @internal
 */
final class AdminPath
{
    public const string ENV = 'LYNX_ADMIN_PREFIX';

    /** Segments of lowercase letters, digits, "-" and "_", separated by "/". */
    private const string PATTERN = '#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#';

    /** Characters of a random path: 20 of 36 is about 103 bits, too many to guess. */
    private const int RANDOM_LENGTH = 20;

    private const string RANDOM_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /** The configured path, without its slashes: "lynx", "admin", "team/cms". */
    public static function current(): string
    {
        return self::validate(Value::string(config('lynx-admin.prefix')));
    }

    /**
     * The path without its outer slashes, or an exception that says what is wrong with it.
     *
     * @throws InvalidAdminPath
     */
    public static function validate(string $path): string
    {
        $path = trim(trim($path), '/');

        if ($path === '') {
            throw new InvalidAdminPath('The admin needs a path: set '.self::ENV.' to one, such as "admin". Without one, its screens would take over the site\'s own URLs.');
        }

        if (preg_match(self::PATTERN, $path) !== 1) {
            throw new InvalidAdminPath("The admin path \"{$path}\" can't be used: ".self::ENV.' takes lowercase letters, digits, "-" and "_", with "/" between segments, such as "admin" or "team/cms".');
        }

        return $path;
    }

    /** A path nobody would guess, for admins that should not be found by trying /admin or /lynx. */
    public static function random(): string
    {
        $path = '';

        for ($i = 0; $i < self::RANDOM_LENGTH; $i++) {
            $path .= self::RANDOM_ALPHABET[random_int(0, strlen(self::RANDOM_ALPHABET) - 1)];
        }

        return $path;
    }
}
