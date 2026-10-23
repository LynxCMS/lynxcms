<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\AdminAccess;

/**
 * A module's screen registered with Admin::screen(): its view, for users with its permission.
 * The view and the permission are route defaults, so route:cache keeps them.
 *
 * @internal
 */
final readonly class ModuleScreenController
{
    public function __construct(private AdminAccess $access) {}

    /** @param view-string $view */
    public function __invoke(string $view, string $permission): View
    {
        abort_unless($this->access->allows($permission), 403);

        return view($view);
    }
}
