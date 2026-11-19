<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * A user whose password a superuser set (ADR-0126). Written by users.create_with_temporary_password and
 * users.temporary_password, removed when the user chooses their own password.
 *
 * @property int $id
 * @property string $user_id
 * @property Carbon $expires_at
 *
 * @internal
 */
final class TemporaryPassword extends Model
{
    #[Override]
    protected $table = 'lynx_temporary_passwords';

    #[Override]
    protected $guarded = [];

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
