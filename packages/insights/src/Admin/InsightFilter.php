<?php

declare(strict_types=1);

namespace LynxCms\Insights\Admin;

use LynxCms\Insights\InsightState;

/**
 * The Insights screen's filters, checked: a state that is not one of the states shows open
 * insights, so a link someone edited by hand still shows a list. Open includes the insights being
 * approved, which have no option of their own: they are open until their steps end.
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
        $states = array_filter(InsightState::cases(), fn (InsightState $state): bool => $state !== InsightState::Approving);

        return [...array_values(array_map(fn (InsightState $state): string => $state->value, $states)), self::ALL];
    }

    /** @return list<string> the stored states the chosen state covers */
    public function covers(): array
    {
        return match ($this->state) {
            self::ALL => array_map(fn (InsightState $state): string => $state->value, InsightState::cases()),
            InsightState::Open->value => [InsightState::Open->value, InsightState::Approving->value],
            default => [$this->state],
        };
    }

    public function isFiltered(): bool
    {
        return $this->state !== InsightState::Open->value || $this->source !== '' || $this->search !== '';
    }
}
