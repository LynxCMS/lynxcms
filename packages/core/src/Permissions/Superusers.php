<?php

declare(strict_types=1);

namespace LynxCms\Core\Permissions;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;

/**
 * Who holds a superuser role (ADR-0033, ADR-0114). Lynx keeps user ids without a foreign key, so
 * a deleted account's assignments stay behind: only accounts the app's user provider still finds
 * count as superusers. The last one is never removed, and neither is the last superuser
 * assignment, which would turn open mode (lynx.permissions.open_until_roles) back on.
 *
 * @internal
 */
final readonly class Superusers
{
    public function __construct(private AuthManager $auth) {}

    /** Whether any superuser's account still exists. */
    public function exist(): bool
    {
        $provider = $this->provider();

        return array_any(array_unique($this->assignments()), fn (string $userId): bool => $this->accountExists($provider, $userId));
    }

    /**
     * Refuses a role change that would leave no superuser.
     *
     * @param  list<int>  $removedAssignmentIds  assignments the change would remove
     */
    public function assertKept(array $removedAssignmentIds, string $key): void
    {
        $all = $this->assignments();
        $provider = $this->provider();
        $existing = array_filter($all, fn (string $userId): bool => $this->accountExists($provider, $userId));
        $removed = array_flip($removedAssignmentIds);

        $leavesNone = $all !== [] && array_diff_key($all, $removed) === [];
        $leavesNoAccount = $existing !== [] && array_diff_key($existing, $removed) === [];

        if ($leavesNone || $leavesNoAccount) {
            throw ValidationException::withMessages([$key => 'This would remove the last superuser. Give another user a superuser role first.']);
        }
    }

    /** @return array<int, string> user ids by assignment id */
    private function assignments(): array
    {
        $assignments = [];

        foreach (RoleAssignment::query()->whereIn('role_id', Role::query()->where('is_superuser', true)->select('id'))->get(['id', 'user_id']) as $assignment) {
            $assignments[Value::int($assignment->getKey())] = Value::string($assignment->getAttribute('user_id'));
        }

        return $assignments;
    }

    /** The default guard's user provider, which signs the app's users in. */
    private function provider(): ?UserProvider
    {
        $guard = $this->auth->guard();
        $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;

        return $provider instanceof UserProvider ? $provider : null;
    }

    /**
     * Without a user provider Lynx can't tell, so every account counts. An id that can't be an
     * integer key is never looked up: PostgreSQL would fail the query, and the transaction with it.
     */
    private function accountExists(?UserProvider $provider, string $userId): bool
    {
        if ($provider === null) {
            return true;
        }

        if ($provider instanceof EloquentUserProvider && $provider->createModel()->getKeyType() === 'int' && ! ctype_digit($userId)) {
            return false;
        }

        return $provider->retrieveById($userId) !== null;
    }
}
