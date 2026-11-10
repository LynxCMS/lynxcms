<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\Home\HomeOverview;
use LynxCms\Admin\Home\HomeScreen;

/**
 * The admin's home screen: a welcome with today's date and common actions, stats, pages with a
 * draft, what is scheduled next, the pages changed last and what modules add (HomeScreen).
 *
 * @internal
 */
final class HomeController
{
    public function __invoke(HomeOverview $overview, HomeScreen $home): View
    {
        $name = data_get(auth()->user(), 'name');

        return view('lynx-admin::home', [
            'name' => is_string($name) && $name !== '' ? $name : null,
            'today' => now()->isoFormat(__('lynx-admin::home.date_format')),
            'actions' => $home->actions(),
            'stats' => $home->stats(),
            'drafts' => $overview->drafts(),
            'upcoming' => $overview->upcoming(),
            'recent' => $overview->recent(),
            'panels' => $home->panels(),
        ]);
    }
}
