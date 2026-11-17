<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\Home\HomeScreen;

/**
 * The admin's home screen: a welcome with today's date and common actions, stats, and the panels
 * of each column (HomeScreen), Lynx's own lists among them (HomeDefaults).
 *
 * @internal
 */
final class HomeController
{
    public function __invoke(HomeScreen $home): View
    {
        $name = data_get(auth()->user(), 'name');

        return view('lynx-admin::home', [
            'name' => is_string($name) && $name !== '' ? $name : null,
            'today' => now()->isoFormat(__('lynx-admin::home.date_format')),
            'actions' => $home->actions(),
            'stats' => $home->stats(),
            'panels' => $home->panels(),
            'sidePanels' => $home->panels(HomeScreen::SIDE),
        ]);
    }
}
