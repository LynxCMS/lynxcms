<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

use Illuminate\Support\HtmlString;
use LynxCms\Admin\View\TailwindMergeMacros;

/**
 * BlatUI button classes and Lucide icons for the page tree's row actions, as plain HTML. Every
 * row carries its action bar (Alpine shows it on the selected row), and rendering it from
 * <x-lynx-admin::ui.button> components made opening a branch of 50 pages three times slower.
 * A test checks that the classes match what the button component renders.
 *
 * @internal
 */
final class TreeUi
{
    /** From components/ui/button.blade.php. */
    private const string BASE = "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive";

    private const array VARIANTS = [
        'ghost' => 'hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50',
    ];

    private const array SIZES = [
        'xs' => 'h-7 gap-1 rounded-md px-2.5 text-xs has-[>svg]:px-2 [&_svg:not([class*=size-])]:size-3.5',
        'icon-xs' => 'size-7 [&_svg:not([class*=size-])]:size-3.5',
    ];

    /** From components/ui/badge.blade.php (variant secondary, size sm), without its focus and link classes: this badge is a plain span. */
    public const string COUNT_BADGE = 'inline-flex items-center justify-center rounded-md border font-medium w-fit whitespace-nowrap shrink-0 gap-1 overflow-hidden border-transparent bg-secondary text-secondary-foreground px-1.5 py-px text-[0.625rem] tabular-nums';

    /** @var array<string, HtmlString> */
    private static array $icons = [];

    /** The classes <x-lynx-admin::ui.button variant=… size=… class=…> would get. */
    public static function button(string $variant, string $size, string $class = ''): string
    {
        return TailwindMergeMacros::merge([self::BASE.' '.self::VARIANTS[$variant].' '.self::SIZES[$size]], $class);
    }

    /** A Lucide icon's SVG, rendered once per request, name and class. */
    public static function icon(string $name, string $class = ''): HtmlString
    {
        return self::$icons[$name.' '.$class] ??= new HtmlString(svg('lucide-'.$name, $class, ['aria-hidden' => 'true'])->toHtml());
    }
}
