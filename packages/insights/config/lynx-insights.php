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

    // The Insights Agent (with lynxcms/ai): it sends page text to the AI provider and uses its
    // credits, so it is off unless turned on. At most `max` insights a report, starting from the
    // `pages` most viewed (or most linked) pages; provider and model default to lynx.ai's.
    'ai' => [
        'enabled' => env('LYNX_INSIGHTS_AGENT', false),
        'max' => 5,
        'pages' => 50,
        'provider' => env('LYNX_INSIGHTS_AGENT_PROVIDER'),
        'model' => env('LYNX_INSIGHTS_AGENT_MODEL'),
    ],

    // The minutes a running insight may go without progress before lynx:doctor warns about it.
    'stuck_minutes' => 30,
];
