<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\Livewire\RoleList;

/**
 * The Roles, Users and General screens, superusers only. A controller rather than closures so route:cache
 * keeps working.
 *
 * @internal
 */
final class SuperuserScreenController
{
    public function roles(): View
    {
        abort_unless(RoleList::currentUserIsSuperuser(), 403);

        return view('lynx-admin::roles.index');
    }

    public function users(): View
    {
        abort_unless(RoleList::currentUserIsSuperuser(), 403);

        return view('lynx-admin::users.index');
    }

    /** Settings > General: the admin's branding. */
    public function general(): View
    {
        abort_unless(RoleList::currentUserIsSuperuser(), 403);

        return view('lynx-admin::general');
    }
}
