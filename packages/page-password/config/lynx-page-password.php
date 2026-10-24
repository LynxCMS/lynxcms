<?php

declare(strict_types=1);

return [
    // How long a page stays unlocked in a browser after its password is typed, in days.
    'remember_days' => (int) env('LYNX_PAGE_PASSWORD_REMEMBER_DAYS', 30),

    // Wrong passwords a protected page accepts per hour from every address together; past it,
    // the page refuses every try until the hour is over. Each address also has 5 tries a minute.
    'max_failures_per_hour' => (int) env('LYNX_PAGE_PASSWORD_MAX_FAILURES', 100),
];
