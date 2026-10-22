<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

/**
 * A superuser invited someone. Dispatched after the invitation commits, so dry runs send nothing.
 *
 * @api
 */
final readonly class UserInvited
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $email,
        public string $token,
    ) {}
}
