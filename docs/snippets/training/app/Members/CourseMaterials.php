<?php

declare(strict_types=1);

namespace App\Members;

use App\Models\Booking;
use Illuminate\Database\Query\Builder;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\VariesByVisitor;
use LynxCms\Core\Pages\Visibility\VisibilityRule;

// #region rule
/**
 * A course's materials page is for the people who booked it: guests and other members don't find
 * it in lists, menus or search. The gate in AtlasServiceProvider guards its URL.
 */
final class CourseMaterials implements VariesByVisitor, VisibilityRule
{
    /** @var array<int|string, list<int>> course page ids each user has a paid booking on */
    private array $courses = [];

    public function apply(Builder $query): void
    {
        $query->whereNotIn('lynx_pages.id', fn (Builder $materials) => $materials
            ->select('id')->from('lynx_pages')
            ->where('template_id', lynx()->templates->get('materials')->id)
            ->whereNotIn('parent_id', $this->bookedCourses() ?: [0]));
    }

    public function allows(Page $page): bool
    {
        return $page->template()->name !== 'materials' || in_array($page->parentId, $this->bookedCourses(), true);
    }

    /** Each member sees their own courses' materials, so caches keep a copy per member. */
    public function audience(): ?string
    {
        return auth()->id() === null ? null : 'member-'.auth()->id();
    }

    /** @return list<int> */
    public function bookedCourses(): array
    {
        $user = auth()->id();

        if ($user === null) {
            return [];
        }

        return $this->courses[$user] ??= Booking::query()->where('user_id', $user)->where('status', 'paid')
            ->pluck('session_id')
            ->map(fn (int $session): ?int => lynx()->pages->get($session)?->parentId)
            ->filter()->unique()->values()->all();
    }
}
// #endregion rule
