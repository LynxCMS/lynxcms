<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Support\Facades\Storage;
use LynxCms\Admin\Branding\Branding;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the prebuilt admin stylesheet from the package, so host apps need no build step
 * and no vendor:publish. The layout adds the file's mtime as a version for cache busting.
 * Also serves the logo uploaded under Settings, which the sign-in screens show to guests,
 * and Lynx's own favicon.
 *
 * @internal
 */
final class AssetController
{
    public const string STYLESHEET = __DIR__.'/../../dist/admin.css';

    public const string SCRIPT = __DIR__.'/../../dist/admin.js';

    /** The Lynx favicons, by extension: committed with the package, not built (ADR-0089). */
    public const array FAVICONS = [
        'svg' => __DIR__.'/../../resources/brand/favicon.svg',
        'ico' => __DIR__.'/../../resources/brand/favicon.ico',
    ];

    private const array FAVICON_TYPES = ['svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];

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

    /** Lynx's own favicon, which the admin links while the site has no branding of its own. */
    public function favicon(string $extension): BinaryFileResponse
    {
        abort_unless(isset(self::FAVICONS[$extension]), 404);

        return $this->file(self::FAVICONS[$extension], self::FAVICON_TYPES[$extension]);
    }

    /**
     * The uploaded logo, from private storage, so no storage:link is needed. Its name holds its
     * hash, so it is cached for good. An SVG is sandboxed: opened on its own, it runs nothing.
     */
    public function logo(string $file): BinaryFileResponse
    {
        $path = Branding::FOLDER.'/'.$file;
        $disk = Storage::disk(Branding::DISK);
        abort_unless(preg_match(Branding::LOGO_FILE, $file) === 1 && $disk->exists($path), 404);
        $isSvg = str_ends_with($file, '.svg');

        return response()->file($disk->path($path), [
            'Content-Type' => $isSvg ? 'image/svg+xml' : (string) $disk->mimeType($path),
            'Cache-Control' => 'public, max-age='.self::ONE_YEAR.', immutable',
            'X-Content-Type-Options' => 'nosniff',
            ...($isSvg ? ['Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox"] : []),
        ]);
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
