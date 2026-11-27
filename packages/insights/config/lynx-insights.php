<?php

declare(strict_types=1);

return [
    // When the scheduler writes a report: null (never), "monthly", or a cron expression.
    'schedule' => env('LYNX_INSIGHTS_SCHEDULE'),

    // The days a report looks back over.
    'period_days' => 30,

    // The days a dismissed insight is not reported again, unless its weight doubles.
    'quiet_days' => 180,

    // The days of page metrics a site needs before sources rank or report by them (ADR-0156).
    'min_days' => 14,

    // The months a popular page goes unchanged before it counts as neglected.
    'neglected_months' => 12,

    // The minutes a running insight may go without progress before lynx:doctor warns about it.
    'stuck_minutes' => 30,
];
