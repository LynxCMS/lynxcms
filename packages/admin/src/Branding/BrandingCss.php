<?php

declare(strict_types=1);

namespace LynxCms\Admin\Branding;

/**
 * The branding's colours as CSS, for a <style> element in the admin's <head> (ADR-0084). The accent
 * tokens are !important, so they win over BlatUI's themes (.dark[data-theme]) and a site's
 * stylesheets as the inline style they replace did; the dark rule's html.dark makes it win over
 * the light one. Dark rules are written only for values the site set for dark mode, so dark mode
 * keeps the light colours until then. Every colour is a checked #rrggbb.
 *
 * @internal
 */
final class BrandingCss
{
    public static function of(Branding $branding): string
    {
        return implode('', array_filter([
            self::rule('html:root', self::important($branding->accentStyle())),
            $branding->darkAccent === null ? '' : self::rule('html.dark:root', self::important($branding->accentStyle(dark: true))),
            self::rule('body[data-lynx-backdrop]', $branding->backgroundStyle()),
            $branding->darkBackground === null ? '' : self::rule('html.dark body[data-lynx-backdrop]', $branding->backgroundStyle(dark: true)),
            self::logos($branding),
        ]));
    }

    /** Both logos are in the page; each mode hides the other's. */
    private static function logos(Branding $branding): string
    {
        if ($branding->darkLogoUrl() === null) {
            return '';
        }

        return 'html.dark .lynx-logo-light{display:none !important}html:not(.dark) .lynx-logo-dark{display:none !important}';
    }

    private static function important(string $declarations): string
    {
        return str_replace(';', ' !important;', $declarations);
    }

    private static function rule(string $selector, string $declarations): string
    {
        return $declarations === '' ? '' : "{$selector}{{$declarations}}";
    }
}
