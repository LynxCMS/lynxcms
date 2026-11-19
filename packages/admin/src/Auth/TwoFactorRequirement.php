<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Database\Eloquent\Model;
use Laravel\Fortify\Features;
use Laravel\Fortify\TwoFactorAuthenticatable;
use LynxCms\Core\Support\Value;

/**
 * Whether the admin requires two-factor authentication (ADR-0128): lynx-admin.auth.require_two_factor,
 * when Lynx manages accounts and two-factor authentication can be set up at all, which needs
 * Fortify's feature and a user model with TwoFactorAuthenticatable. Without them nobody could
 * meet the requirement, so it is not applied and lynx:doctor says why.
 *
 * @internal
 */
final class TwoFactorRequirement
{
    public static function isRequired(): bool
    {
        return Value::bool(config('lynx-admin.auth.require_two_factor', true)) && AdminAuth::managesAccounts();
    }

    /** Required, and the app can offer it: Fortify's feature and the model's trait. */
    public static function applies(): bool
    {
        return self::isRequired() && self::canBeSetUp();
    }

    public static function canBeSetUp(): bool
    {
        $model = config('auth.providers.users.model');

        return Features::canManageTwoFactorAuthentication()
            && is_string($model) && is_subclass_of($model, Model::class)
            && in_array(TwoFactorAuthenticatable::class, class_uses_recursive($model), true);
    }

    /** Whether the signed in user still has to set it up. */
    public static function isMissing(): bool
    {
        $user = TwoFactorState::user();

        return self::applies() && $user !== null && ! TwoFactorState::isEnabled($user);
    }
}
