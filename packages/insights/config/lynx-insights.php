<?php

declare(strict_types=1);

return [
    // When the scheduler writes a report: null (never), "monthly", or a cron expression.
    'schedule' => env('LYNX_INSIGHTS_SCHEDULE'),

    // The days a report looks back over.
    'period_days' => 30,

    // The days a dismissed insight is not reported again, unless its weight doubles.
    'quiet_days' => 180,

    // The minutes a running insight may go without progress before lynx:doctor warns about it.
    'stuck_minutes' => 30,
];
