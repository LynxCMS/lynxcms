<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Contracts\Auth\Authenticatable;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;

/**
 * Removes the Lynx roles of a user the app is about to delete.
 */
final class ForgetLynxRoles
{
    public function __invoke(Authenticatable $user): void
    {
        $id = $user->getAuthIdentifier();

        lynx()->actingAs(Actor::system(), fn () => lynx()->run(AssignRoles::class, [
            'user' => is_scalar($id) ? (string) $id : '',
            'roles' => [],
        ]));
    }
}
