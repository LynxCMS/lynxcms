<?php

declare(strict_types=1);

namespace LynxCms\Admin\Branding;

/**
 * Keeps a site's accent readable in dark mode (WCAG 1.4.3). A site that sets an accent for light
 * mode only would otherwise get the same colour on the admin's dark background, where a dark
 * accent fails as link text and focus ring. The accent is mixed with white in small steps until
 * it reaches 4.5:1 against that background; light mode keeps the site's own colour.
 *
 * @internal
 */
final class AccentContrast
{
    /** The admin's dark mode background (BlatUI's --background under .dark), as axe measures it. */
    public const string DARK_SURFACE = '#1c1917';

    private const float MINIMUM = 4.5;

    private const int STEPS = 20;

    public static function onDark(string $accent): string
    {
        for ($step = 0; $step <= self::STEPS; $step++) {
            $candidate = self::towardWhite($accent, $step / self::STEPS);

            if (self::ratio($candidate, self::DARK_SURFACE) >= self::MINIMUM) {
                return $candidate;
            }
        }

        return '#ffffff';
    }

    /** WCAG contrast ratio between two #rrggbb colours. */
    public static function ratio(string $first, string $second): float
    {
        [$lighter, $darker] = [max(self::luminance($first), self::luminance($second)), min(self::luminance($first), self::luminance($second))];

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function luminance(string $hex): float
    {
        $channels = array_map(function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function towardWhite(string $hex, float $amount): string
    {
        return '#'.implode('', array_map(
            fn (string $pair): string => str_pad(dechex((int) round(hexdec($pair) + (255 - hexdec($pair)) * $amount)), 2, '0', STR_PAD_LEFT),
            str_split(strtolower(substr($hex, 1)), 2),
        ));
    }
}
