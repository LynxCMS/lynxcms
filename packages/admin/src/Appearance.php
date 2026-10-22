<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use LynxCms\Core\Support\Value;

/**
 * Turns config lynx-admin.appearance into what BlatUI's presets need (ADR-0034): attributes on
 * <html> (['base' => 'zinc'] gives data-base="zinc") and the web fonts the chosen fonts use.
 * Every option of BlatUI's customizer is here; values BlatUI does not know are left out.
 *
 * @api
 */
final class Appearance
{
    /** Config key => [attribute on <html>, values BlatUI's stylesheet knows]. */
    public const array OPTIONS = [
        'base' => ['data-base', ['neutral', 'stone', 'zinc', 'slate', 'gray', 'mauve', 'olive', 'mist', 'taupe']],
        'theme' => ['data-theme', ['default', 'amber', 'blue', 'cyan', 'emerald', 'fuchsia', 'green', 'indigo', 'lime', 'orange', 'pink', 'purple', 'red', 'rose', 'sky', 'teal', 'violet', 'yellow']],
        'radius' => ['data-radius', ['0', '0.3', '0.5', '0.625', '0.75', '1']],
        'font' => ['data-font', ['sans', ...self::FONT_NAMES]],
        'heading_font' => ['data-font-heading', ['sans', ...self::FONT_NAMES]],
        'shadow' => ['data-shadow', ['none', 'sm', 'default', 'lg', 'xl']],
        'spacing' => ['data-spacing', ['compact', 'default', 'comfortable']],
        'tracking' => ['data-tracking', ['tight', 'normal', 'wide']],
        'input_style' => ['data-input-style', ['outline', 'fill', 'inset']],
    ];

    /** Font options besides "sans" (BlatUI's default, or for headings: the body font). */
    private const array FONT_NAMES = ['system', 'serif', 'mono', 'inter', 'geist', 'manrope', 'jakarta', 'space-grotesk', 'dm-sans', 'outfit', 'sora', 'lora', 'source-serif'];

    /** Font option => family on fonts.bunny.net (a GDPR friendly Google Fonts mirror). System stacks need none. */
    private const array WEB_FONTS = [
        'sans' => 'instrument-sans',
        'inter' => 'inter',
        'geist' => 'geist',
        'manrope' => 'manrope',
        'jakarta' => 'plus-jakarta-sans',
        'space-grotesk' => 'space-grotesk',
        'dm-sans' => 'dm-sans',
        'outfit' => 'outfit',
        'sora' => 'sora',
        'lora' => 'lora',
        'source-serif' => 'source-serif-4',
    ];

    private const string FONTS_ENDPOINT = 'https://fonts.bunny.net/css?family=';

    private const string FONT_WEIGHTS = '400,500,600,700';

    /**
     * The attributes as HTML, e.g. 'data-base="zinc" data-theme="blue"'.
     *
     * @param  array<array-key, mixed>  $appearance
     */
    public static function html(array $appearance): string
    {
        $pairs = [];

        foreach (self::attributes($appearance) as $attribute => $value) {
            $pairs[] = $attribute.'="'.e($value).'"';
        }

        return implode(' ', $pairs);
    }

    /**
     * @param  array<array-key, mixed>  $appearance
     * @return array<string, string> attribute => value
     */
    public static function attributes(array $appearance): array
    {
        $attributes = [];

        foreach (self::OPTIONS as $key => [$attribute, $values]) {
            $value = self::value($appearance, $key);

            if ($value !== null) {
                $attributes[$attribute] = $value;
            }
        }

        return $attributes;
    }

    /**
     * The stylesheet loading the web fonts the body and heading fonts use, or null when both are
     * system stacks. A heading font of "sans" follows the body font.
     *
     * @param  array<array-key, mixed>  $appearance
     */
    public static function fontsUrl(array $appearance): ?string
    {
        $body = self::value($appearance, 'font') ?? 'sans';
        $heading = self::value($appearance, 'heading_font') ?? 'sans';
        $families = array_unique(array_filter([self::WEB_FONTS[$body] ?? null, $heading === 'sans' ? null : self::WEB_FONTS[$heading] ?? null]));

        if ($families === []) {
            return null;
        }

        return self::FONTS_ENDPOINT.implode('|', array_map(fn (string $family): string => $family.':'.self::FONT_WEIGHTS, $families)).'&display=swap';
    }

    /**
     * The configured value when BlatUI knows it, else null.
     *
     * @param  array<array-key, mixed>  $appearance
     */
    private static function value(array $appearance, string $key): ?string
    {
        $value = Value::string($appearance[$key] ?? null);

        return in_array($value, self::OPTIONS[$key][1], true) ? $value : null;
    }
}
