<?php

declare(strict_types=1);

namespace App\Lynx;

use Illuminate\Database\Query\Builder;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\VariesByVisitor;
use LynxCms\Core\Pages\Visibility\VisibilityRule;

// #region rule
/**
 * Pages with the "members_only" toggle on are found only by signed in users: guests don't see
 * them in lists, selectors, menus or page references. A gate guards their URL (members-only.php).
 */
final class MembersOnly implements VariesByVisitor, VisibilityRule
{
    /** Leaves members-only pages out of every selector query, for guests. */
    public function apply(Builder $query): void
    {
        if (auth()->check()) {
            return;
        }

        $query->whereNotIn('lynx_pages.id', fn (Builder $members) => $members
            ->select('page_id')
            ->from('lynx_f_members_only')
            ->where('data', true));
    }

    /** The same rule for a single page, such as one a page reference field holds. */
    public function allows(Page $page): bool
    {
        return auth()->check() || $page->members_only !== true;
    }

    /** Guests all see the same pages, so caches can share what they make for them. */
    public function audience(): ?string
    {
        return auth()->check() ? 'members' : null;
    }
}
// #endregion rule
