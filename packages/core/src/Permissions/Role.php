<?php

declare(strict_types=1);

namespace LynxCms\Core\Permissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LynxCms\Core\Support\Tables;
use Override;

/**
 * A role: a name, whether it may do everything, and its grants (ADR-0033). A system table, so
 * Eloquent is allowed (AGENTS.md rule 8).
 *
 * @property int $id
 * @property string $name
 * @property string $label
 * @property bool $is_superuser
 *
 * @api
 */
final class Role extends Model
{
    #[Override]
    protected $table = Tables::ROLES;

    #[Override]
    protected $guarded = [];

    /**
     * @return HasMany<RoleGrant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(RoleGrant::class);
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /** Give the role to a host app user; assigning twice changes nothing. */
    public function assign(int|string $userId, ?string $assignedBy = null): void
    {
        $this->assignments()->firstOrCreate(['user_id' => (string) $userId], ['assigned_by' => $assignedBy]);
    }

    protected function casts(): array
    {
        return ['is_superuser' => 'boolean'];
    }
}
