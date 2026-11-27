<?php

declare(strict_types=1);

namespace LynxCms\Insights;

/**
 * Something that reads the site and suggests changes, as a doctor check reports problems
 * (ADR-0155). A source only reads: its insights change the site only once an editor approves
 * one, and then as that editor. Register one with InsightSourceRegistry::add() from a service provider.
 *
 * @api
 */
interface InsightSource
{
    /** A short stable key, e.g. "missing-translations": it is stored with every insight. */
    public function key(): string;

    /** What the source looks for, as the report names it. */
    public function label(): string;

    /**
     * What this source suggests for the period; empty when nothing is worth changing.
     *
     * @return list<Insight>
     */
    public function insights(InsightPeriod $period): array;
}
