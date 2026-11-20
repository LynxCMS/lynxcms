<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Http\Request;

/**
 * The password typed to sign in counts as just confirmed, so password.confirm screens (account
 * security) do not ask for it again straight away. Validated fires only when a typed password
 * matched: a "Keep me signed in" cookie or a passkey confirms nothing. Registered after
 * RefuseExpiredTemporaryPassword, so an expired temporary password confirms nothing either.
 *
 * @internal
 */
final readonly class ConfirmPasswordOnSignIn
{
    public function __construct(private Request $request) {}

    public function handle(): void
    {
        if ($this->request->hasSession()) {
            $this->request->session()->passwordConfirmed();
        }
    }
}
