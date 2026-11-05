<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

use Closure;

/**
 * Filters modules add to the page tree's Details view, after Lynx's own (ADR-0103). Each is a
 * selector term over the same pages as the built-in filters, with a label and, optionally, a hint
 * shown on hover that says what the filter finds. Register them in a service provider:
 * app(TreeFilterRegistry::class)->add(fn (): array => ['reviewed' => ['label' => 'Reviewed', 'term' => 'reviewed=1']]).
 * The closure runs when the tree shows its filters, so it can read languages or settings.
 *
 * @api
 */
final class TreeFilterRegistry
{
    /** @var list<Closure(): array<string, array{label: string, term: string, hint?: string}>> */
    private array $sources = [];

    /** @param  Closure(): array<string, array{label: string, term: string, hint?: string}>  $filters  by key */
    public function add(Closure $filters): void
    {
        $this->sources[] = $filters;
    }

    /** @return array<string, array{label: string, term: string, hint?: string}> every module filter, by key */
    public function all(): array
    {
        $all = [];

        foreach ($this->sources as $source) {
            $all += $source();
        }

        return $all;
    }
}
