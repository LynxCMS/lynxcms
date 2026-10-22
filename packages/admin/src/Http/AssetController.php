<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the prebuilt admin stylesheet from the package, so host apps need no build step
 * and no vendor:publish. The layout adds the file's mtime as a version for cache busting.
 *
 * @internal
 */
final class AssetController
{
    public const string STYLESHEET = __DIR__.'/../../dist/admin.css';

    public const string SCRIPT = __DIR__.'/../../dist/admin.js';

    private const int ONE_YEAR = 31_536_000;

    public function __invoke(): BinaryFileResponse
    {
        return $this->css(self::STYLESHEET);
    }

    /** The admin's JavaScript (the rich text editor), bundled from resources/js. */
    public function script(): BinaryFileResponse
    {
        return response()->file(self::SCRIPT, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age='.self::ONE_YEAR.', immutable',
        ]);
    }

    /** Cache-busting version for the script URL. */
    public static function scriptVersion(): string
    {
        return (string) (filemtime(self::SCRIPT) ?: '0');
    }

    private function css(string $path): BinaryFileResponse
    {
        return response()->file($path, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age='.self::ONE_YEAR.', immutable',
        ]);
    }

    /** Cache-busting version for the stylesheet URL. */
    public static function version(): string
    {
        return (string) (filemtime(self::STYLESHEET) ?: '0');
    }
}
