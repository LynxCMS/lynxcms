<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Access;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\Visibility\VariesByVisitor;
use LynxCms\Core\Pages\Visibility\VisibilityRule;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Leaves locked pages out of what visitors find (ADR-0062). A page is locked when the nearest
 * page at or above it with a password is not unlocked in the browser, and the visitor is not a
 * signed-in user who may view that page in the admin.
 *
 * @internal
 */
final readonly class PasswordVisibility implements VariesByVisitor, VisibilityRule
{
    private const string LOCKED = 'lynx.page_password.locked';

    public function __construct(
        private ProtectedRoots $roots,
        private Unlocks $unlocks,
        private PageLoader $loader,
        private Authorizer $authorizer,
        private ActionRunner $runner,
    ) {}

    /** Null while every protected page is locked for this visitor, as for anyone without a cookie or account. */
    public function audience(): ?string
    {
        $open = array_values(array_diff($this->roots->ids(), $this->lockedRoots()));

        return $open === [] ? null : 'page-password:'.implode(',', $open);
    }

    public function apply(Builder $query): void
    {
        $locked = $this->lockedRoots();

        if ($locked === []) {
            return;
        }

        // Not under a locked root, unless a nearer root (its own password) sits between them.
        $query->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))->from(Tables::PAGE_CLOSURE.' as pw_c')
            ->whereColumn('pw_c.descendant_id', Tables::PAGES.'.id')
            ->whereIn('pw_c.ancestor_id', $locked)
            ->whereNotExists(fn (Builder $nearer) => $nearer->select(DB::raw(1))->from(Tables::PAGE_CLOSURE.' as pw_n')
                ->whereColumn('pw_n.descendant_id', 'pw_c.descendant_id')
                ->whereIn('pw_n.ancestor_id', $this->roots->ids())
                ->whereColumn('pw_n.depth', '<', 'pw_c.depth')));
    }

    public function allows(Page $page): bool
    {
        $locked = $this->lockedRoots();

        if ($locked === [] || $page->id === null) {
            return true;
        }

        $nearest = $this->nearestRoot((int) $page->id);

        return $nearest === null || ! in_array($nearest, $locked, true);
    }

    /** The page with a password that protects this one: itself, or the nearest above it. */
    public function nearestRoot(int $pageId): ?int
    {
        if ($this->roots->ids() === []) {
            return null;
        }

        $nearest = DB::table(Tables::PAGE_CLOSURE)->where('descendant_id', $pageId)
            ->whereIn('ancestor_id', $this->roots->ids())->orderBy('depth')->value('ancestor_id');

        return $nearest === null ? null : Value::int($nearest);
    }

    /** Forgets the locked pages worked out for this request, after a page or password changes. */
    public function flush(): void
    {
        request()->attributes->remove(self::LOCKED);
    }

    /**
     * Protected pages this visitor has not unlocked, worked out once per request.
     *
     * @return list<int>
     */
    private function lockedRoots(): array
    {
        $request = request();

        if (! $request->attributes->has(self::LOCKED)) {
            $request->attributes->set(self::LOCKED, array_values(array_filter($this->roots->ids(), fn (int $id): bool => ! $this->unlocks->isUnlocked($id) && ! $this->canView($id))));
        }

        return Value::ints($request->attributes->get(self::LOCKED));
    }

    /** Signed-in users see the pages their roles let them view in the admin (ADR-0033). */
    private function canView(int $pageId): bool
    {
        if (Auth::user() === null) {
            return false;
        }

        $page = $this->loader->find($pageId);

        return $page instanceof Page && $this->authorizer->allows($this->runner->actor(), 'pages.view', $page);
    }
}
