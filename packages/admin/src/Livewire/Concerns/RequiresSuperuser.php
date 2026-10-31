<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Value;

/**
 * Screens that manage roles: superusers only (ADR-0033). Until any user has a role every signed
 * in user counts as one, so the first superuser can be set up.
 *
 * @internal
 */
trait RequiresSuperuser
{
    /** Checked for the actor the actions run as, so the screen and the actions it calls agree. */
    public static function currentUserIsSuperuser(): bool
    {
        return app(Authorizer::class)->isSuperuser(app(ActionRunner::class)->actor());
    }

    private function authorizeSuperuser(): void
    {
        abort_unless(self::currentUserIsSuperuser(), 403, Value::string(__('lynx-admin::roles.denied')));
    }
}
