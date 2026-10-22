<?php

declare(strict_types=1);

namespace LynxCms\Menus\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * A page a menu build depends on (ADR-0068): kind "page" when the build shows or depends on the
 * page, "children" when it lists the page's children.
 *
 * @property int $menu_id
 * @property int $page_id
 * @property string $kind
 *
 * @internal
 */
final class MenuBuildPage extends Model
{
    public const string TABLE = 'lynx_menu_build_pages';

    public const string PAGE = 'page';

    public const string CHILDREN = 'children';

    #[Override]
    public $timestamps = false;

    #[Override]
    public $incrementing = false;

    #[Override]
    protected $table = self::TABLE;

    #[Override]
    protected $guarded = [];
}
