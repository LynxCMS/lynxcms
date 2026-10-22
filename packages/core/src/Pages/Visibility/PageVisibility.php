<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Visibility;

use Closure;
use Illuminate\Database\Query\Builder;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;

/**
 * Which pages visitors may see (ADR-0062). Modules add rules in a service provider:
 * app(PageVisibility::class)->add(new MyRule). While rules are active, selectors and page
 * reference fields leave out the pages a rule refuses. They apply during HTTP requests, and in
 * console processes inside asVisitor() (the page cache warmer); they are switched off by the
 * WithoutPageVisibility middleware (admin and MCP routes) and inside withoutRules().
 * lynx()->pages->get() is never filtered.
 *
 * @api
 */
final class PageVisibility
{
    /** @var list<VisibilityRule> */
    private array $rules = [];

    private int $suspended = 0;

    private int $asVisitor = 0;

    public function add(VisibilityRule $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * Whether rules narrow what is found now: during HTTP requests and inside asVisitor(), not in
     * commands, queue workers or the scheduler (the test suite runs in the console too, and keeps them).
     */
    public function isActive(): bool
    {
        return $this->rules !== [] && $this->suspended === 0 && ($this->asVisitor > 0 || ! app()->runningInConsole() || app()->runningUnitTests());
    }

    /**
     * Runs $work with the rules on, as for a visitor, even in a console process: for commands that
     * render what visitors see, such as warming the page cache.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function asVisitor(Closure $work): mixed
    {
        $this->asVisitor++;

        try {
            return $work();
        } finally {
            $this->asVisitor--;
        }
    }

    /**
     * Whether the current visitor sees what a visitor with no cookies and no account sees, so work
     * done for that default visitor can be shared (ADR-0068). False while rules are switched off,
     * as on admin routes, where every page shows.
     */
    public function seesDefault(): bool
    {
        if ($this->rules === []) {
            return true;
        }

        return $this->isActive() && array_all($this->rules, fn (VisibilityRule $rule): bool => $rule instanceof VariesByVisitor && $rule->audience() === null);
    }

    /** Narrows a selector query over lynx_pages, while rules are active. */
    public function apply(Builder $query): void
    {
        foreach ($this->isActive() ? $this->rules : [] as $rule) {
            $rule->apply($query);
        }
    }

    /** Whether the page may be shown now: always, while rules are off. */
    public function allows(Page $page): bool
    {
        return ! $this->isActive() || array_all($this->rules, fn (VisibilityRule $rule): bool => $rule->allows($page));
    }

    /** The pages that may be shown now, in their order. */
    public function only(PageArray $pages): PageArray
    {
        return $this->isActive() ? new PageArray(array_values(array_filter($pages->all(), $this->allows(...)))) : $pages;
    }

    /**
     * Runs $work with every page visible, e.g. a route that exports content.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function withoutRules(Closure $work): mixed
    {
        $this->suspend();

        try {
            return $work();
        } finally {
            $this->resume();
        }
    }

    /** Switches the rules off until resume(); calls nest. */
    public function suspend(): void
    {
        $this->suspended++;
    }

    public function resume(): void
    {
        $this->suspended = max(0, $this->suspended - 1);
    }
}
