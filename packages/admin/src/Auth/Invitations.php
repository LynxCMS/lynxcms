<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use LogicException;
use LynxCms\Core\Support\Value;

/**
 * Invitation links (ADR-0041): tokens from their own broker and their own table
 * (lynx_invitation_tokens), so they last days (lynx-admin.auth.invitation_days) while password
 * resets last an hour, and neither kind of link opens the other's form.
 *
 * @internal
 */
final class Invitations
{
    public const string BROKER = 'lynx-invitations';

    private const int MINUTES_PER_DAY = 1440;

    private const int DEFAULT_DAYS = 7;

    public const string TABLE = 'lynx_invitation_tokens';

    /** On register: the broker shares the app's user provider, with a table of its own. */
    public static function configureBroker(): void
    {
        $users = (array) config('auth.passwords.users', []);
        config(['auth.passwords.'.self::BROKER => [
            'provider' => $users['provider'] ?? 'users',
            'table' => self::TABLE,
            'expire' => self::days() * self::MINUTES_PER_DAY,
            'throttle' => 0,
        ]]);
    }

    public static function broker(): PasswordBroker
    {
        $broker = Password::broker(self::BROKER);

        return $broker instanceof PasswordBroker ? $broker : throw new LogicException('Invitations need Laravel\'s password broker.');
    }

    public static function days(): int
    {
        return max(1, Value::int(config('lynx-admin.auth.invitation_days'), self::DEFAULT_DAYS));
    }

    public static function url(string $token, string $email): string
    {
        return route('lynx.admin.invitation', ['token' => $token, 'email' => $email]);
    }
}
