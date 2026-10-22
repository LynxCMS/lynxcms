<?php

declare(strict_types=1);

namespace LynxCms\Core\Permissions;

use Illuminate\Database\Eloquent\Model;
use LynxCms\Core\Support\Tables;
use Override;

/**
 * One permission a role grants, optionally limited to template names and to branches (page ids
 * whose subtree is covered). Null means no limit.
 *
 * @property int $id
 * @property int $role_id
 * @property string $permission
 * @property list<string>|null $templates
 * @property list<int>|null $branches
 *
 * @api
 */
final class RoleGrant extends Model
{
    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = Tables::ROLE_PERMISSIONS;

    #[Override]
    protected $guarded = [];

    protected function casts(): array
    {
        return ['templates' => 'array', 'branches' => 'array'];
    }
}
