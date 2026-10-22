<?php

declare(strict_types=1);

namespace LynxCms\Menus\View;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Turns an item's icon value into HTML. By default the value is a list of CSS classes for an
 * icon font: <span class="lynx-menu-icon fa fa-home" aria-hidden="true"></span>. Apps that use
 * SVG icons register their own renderer, e.g. lynx()->menus()->renderIconsUsing(fn ($icon) => svg($icon)).
 *
 * @api
 */
final class IconRenderer
{
    /** @var (Closure(string, string): (Htmlable|string))|null */
    private ?Closure $renderer = null;

    /**
     * @param  Closure(string $icon, string $position): (Htmlable|string)  $renderer  $position is "before" or "after"; the result is output as HTML
     */
    public function using(Closure $renderer): void
    {
        $this->renderer = $renderer;
    }

    public function render(?string $icon, string $position): Htmlable
    {
        if ($icon === null || $icon === '') {
            return new HtmlString('');
        }

        if ($this->renderer instanceof Closure) {
            $html = ($this->renderer)($icon, $position);

            return $html instanceof Htmlable ? $html : new HtmlString($html);
        }

        return new HtmlString('<span class="lynx-menu-icon lynx-menu-icon-'.e($position).' '.e($icon).'" aria-hidden="true"></span>');
    }
}
