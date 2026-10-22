<?php

declare(strict_types=1);

namespace LynxCms\Menus\View;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use LynxCms\Menus\Menus;

/**
 * <x-lynx-menu name="top" view="lynx-menus::menus.dropdown" class="…" />
 *
 * Renders a menu with any Blade view. The view receives $menu (a resolved Menu) and $attributes.
 * Bundled views: lynx-menus::menus.list (plain nested list), .dropdown (opens on hover and focus)
 * and .accordion (opens on click, no JavaScript).
 *
 * @api
 */
final class MenuComponent extends Component
{
    public const string DEFAULT_VIEW = 'lynx-menus::menus.list';

    public function __construct(public string $name, public string $view = self::DEFAULT_VIEW) {}

    public function render(): View|string
    {
        $menu = app(Menus::class)->get($this->name);
        $views = app(ViewFactory::class);

        if (! $views->exists($this->view)) {
            throw new InvalidArgumentException("Menu view [{$this->view}] does not exist.");
        }

        return $menu === null ? '' : $views->make($this->view, ['menu' => $menu]);
    }
}
