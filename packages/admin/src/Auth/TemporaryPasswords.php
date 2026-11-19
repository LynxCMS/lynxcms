<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;

/**
 * Gives a user a temporary password (ADR-0126): random, shown once to the superuser who asked,
 * stored only as a hash, and good for lynx-admin.auth.temporary_password_days. A pending
 * invitation ends, since its link would set a password too.
 *
 * @internal
 */
final class TemporaryPasswords
{
    /** Letters in both cases, digits and symbols. */
    public const int LENGTH = 20;

    private const int DEFAULT_DAYS = 1;

    /** @return array{password: string, expires_at: string} */
    public function give(Authenticatable $user): array
    {
        if (! $user instanceof Model) {
            throw ValidationException::withMessages(['user' => __('lynx-admin::users.temporary.unsupported')]);
        }

        $password = $this->generate();
        $user->forceFill(['password' => Hash::make($password)])->save();
        $id = Value::string($user->getAuthIdentifier());
        $record = TemporaryPassword::query()->updateOrCreate(['user_id' => $id], ['expires_at' => now()->addDays(self::days())]);
        $this->endInvitation($user, $id);

        return ['password' => $password, 'expires_at' => $record->expires_at->toIso8601String()];
    }

    /** Str::password() always mixes letters, digits and symbols; this makes sure both cases are in. */
    private function generate(): string
    {
        do {
            $password = Str::password(self::LENGTH);
        } while (preg_match('/[a-z]/', $password) !== 1 || preg_match('/[A-Z]/', $password) !== 1);

        return $password;
    }

    public static function days(): int
    {
        return max(1, Value::int(config('lynx-admin.auth.temporary_password_days'), self::DEFAULT_DAYS));
    }

    private function endInvitation(Authenticatable $user, string $id): void
    {
        Invitation::query()->where('user_id', $id)->delete();

        if ($user instanceof CanResetPassword) {
            Invitations::broker()->getRepository()->delete($user);
        }
    }
}
