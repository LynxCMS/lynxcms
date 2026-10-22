<?php

declare(strict_types=1);

namespace LynxCms\Admin\Users;

use Illuminate\Contracts\Auth\CanResetPassword;

/**
 * The host app's users as the Users screen sees them (ADR-0033). Lynx never owns users; apps with
 * their own user store bind another implementation.
 *
 * @api
 */
interface LynxUsers
{
    /**
     * @return list<array{id: string, label: string, detail: string}>
     */
    public function search(string $query, int $limit): array;

    public function emailExists(string $email): bool;

    /**
     * A new user for an invitation, with an unusable random password until they choose one.
     */
    public function create(string $name, string $email): CanResetPassword;
}
