<?php

declare(strict_types=1);

namespace LynxCms\Menus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * A menu built in one language for the default visitor (ADR-0068).
 *
 * @property int $id
 * @property int $menu_id
 * @property string $menu_name
 * @property int $language_id
 * @property string $data JSON, see StoredMenu
 * @property Carbon $built_at
 *
 * @internal
 */
final class MenuBuild extends Model
{
    public const string TABLE = 'lynx_menu_builds';

    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = self::TABLE;

    #[Override]
    protected $guarded = [];
}
