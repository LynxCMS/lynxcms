<?php

declare(strict_types=1);

namespace LynxCms\Menus\Support;

use LynxCms\Core\Fields\LinkRel;

/**
 * Safety rules for custom links, applied when saving and again when rendering.
 *
 * @api
 */
final class LinkRules
{
    /** Schemes a custom link may use. Anything else (javascript:, data:, vbscript:…) is refused. */
    public const array SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** Browser keywords or a frame name. */
    private const string TARGET_PATTERN = '/^(?:_blank|_self|_parent|_top|[A-Za-z][A-Za-z0-9_-]{0,63})$/';

    /**
     * Relative URLs, fragments and allowed schemes pass. Browsers ignore whitespace and control
     * characters inside a scheme ("java\tscript:"), so they are removed before the check, and they
     * read "\\host" and "/\\host" like "//host": a protocol-relative link to another site.
     */
    public static function isSafeUrl(string $url): bool
    {
        $compact = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if ($compact === '' || str_starts_with(str_replace('\\', '/', $compact), '//')) {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $compact, $match) !== 1) {
            return true;
        }

        return in_array(strtolower($match[1]), self::SCHEMES, true);
    }

    public static function isValidTarget(string $target): bool
    {
        return preg_match(self::TARGET_PATTERN, $target) === 1;
    }

    /** rel value for a link: the chosen relations, plus noopener for new windows (LinkRel). */
    public static function relFor(?string $target, string $chosen = ''): ?string
    {
        $opensNewWindow = ! in_array($target, [null, '_self', '_parent', '_top'], true);

        return LinkRel::for($chosen, $opensNewWindow);
    }
}
