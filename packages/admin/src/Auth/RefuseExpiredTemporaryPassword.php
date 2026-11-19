<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Auth\Events\Validated;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use LynxCms\Core\Support\Value;

/**
 * A temporary password past its days signs no one in (ADR-0126). Validated fires once the
 * password matched and before the session starts, with Lynx's sign-in screens or the app's.
 *
 * @internal
 */
final class RefuseExpiredTemporaryPassword
{
    public function handle(Validated $event): void
    {
        $temporary = TemporaryPassword::query()->where('user_id', Value::string($event->user->getAuthIdentifier()))->first();

        if ($temporary?->hasExpired() === true) {
            throw ValidationException::withMessages([Fortify::username() => __('lynx-admin::auth.temporary.expired')]);
        }
    }
}
