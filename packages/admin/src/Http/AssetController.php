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

    /** Shown when the assets were not built: they are not committed in the monorepo (ADR-0071). */
    private const string NOT_BUILT = 'The admin assets are not built. Run composer assets (npm --prefix packages/admin ci once).';

    public function __invoke(): BinaryFileResponse
    {
        return $this->file(self::STYLESHEET, 'text/css; charset=utf-8');
    }

    /** The admin's JavaScript (the rich text editor), bundled from resources/js. */
    public function script(): BinaryFileResponse
    {
        return $this->file(self::SCRIPT, 'text/javascript; charset=utf-8');
    }

    /** Cache-busting version for the stylesheet URL. */
    public static function version(): string
    {
        return self::versionOf(self::STYLESHEET);
    }

    /** Cache-busting version for the script URL. */
    public static function scriptVersion(): string
    {
        return self::versionOf(self::SCRIPT);
    }

    /** The file's modification time, or "0" when it was not built, so pages still render. */
    public static function versionOf(string $path): string
    {
        return is_file($path) ? (string) (filemtime($path) ?: '0') : '0';
    }

    private function file(string $path, string $contentType): BinaryFileResponse
    {
        abort_unless(is_file($path), 404, self::NOT_BUILT);

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age='.self::ONE_YEAR.', immutable',
        ]);
    }
}
