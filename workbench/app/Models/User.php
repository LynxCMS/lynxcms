<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Override;

/**
 * The workbench's user, as a host app would have one: notifiable for password reset emails, with
 * Fortify's two-factor authentication and passkeys (ADR-0041).
 */
final class User extends Authenticatable implements PasskeyUser
{
    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    #[Override]
    protected $guarded = [];

    #[Override]
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'two_factor_confirmed_at' => 'datetime'];
    }
}
