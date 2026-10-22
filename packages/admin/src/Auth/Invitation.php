<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * An invitation the invited user has not accepted yet (ADR-0041). Written by users.invite and
 * users.resend_invitation, removed when the user sets a password.
 *
 * @property int $id
 * @property string $user_id
 * @property string $email
 * @property Carbon $sent_at
 *
 * @api
 */
final class Invitation extends Model
{
    #[Override]
    protected $table = 'lynx_invitations';

    #[Override]
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
