<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Visibility;

use Illuminate\Database\Query\Builder;
use LynxCms\Core\Pages\Page;

/**
 * A rule about which pages visitors may see (ADR-0062), registered with PageVisibility. Both
 * methods must agree: listings use apply(), single pages allows().
 *
 * @api
 */
interface VisibilityRule
{
    /** Narrows a selector query over lynx_pages to the pages the visitor may see. */
    public function apply(Builder $query): void;

    /** Whether the visitor may see the page. */
    public function allows(Page $page): bool;
}
