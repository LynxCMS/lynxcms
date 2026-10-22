<?php

declare(strict_types=1);

namespace LynxCms\Core\Permissions;

use Illuminate\Database\Eloquent\Model;
use LynxCms\Core\Support\Tables;
use Override;

/**
 * A host app user holding a role. User ids are stored as strings, so any key type works.
 *
 * @property int $id
 * @property int $role_id
 * @property string $user_id
 * @property string|null $assigned_by
 *
 * @api
 */
final class RoleAssignment extends Model
{
    #[Override]
    protected $table = Tables::ROLE_USER;

    #[Override]
    protected $guarded = [];
}
