<?php

declare(strict_types=1);

namespace LynxCms\Insights\Admin;

use LynxCms\Insights\InsightState;

/**
 * The Insights screen's filters, checked: a state that is not one of the states shows open
 * insights, so a link someone edited by hand still shows a list.
 *
 * @internal
 */
final readonly class InsightFilter
{
    public const string ALL = 'all';

    public string $state;

    public string $search;

    public function __construct(string $state, public string $source, string $search)
    {
        $this->state = in_array($state, self::states(), true) ? $state : InsightState::Open->value;
        $this->search = trim($search);
    }

    /** @return list<string> the states the filter offers, open first, then all */
    public static function states(): array
    {
        return [...array_map(fn (InsightState $state): string => $state->value, InsightState::cases()), self::ALL];
    }

    public function isFiltered(): bool
    {
        return $this->state !== InsightState::Open->value || $this->source !== '' || $this->search !== '';
    }
}
