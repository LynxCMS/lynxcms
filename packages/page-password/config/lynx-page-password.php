<?php

declare(strict_types=1);

return [
    // How long a page stays unlocked in a browser after its password is typed, in days.
    'remember_days' => (int) env('LYNX_PAGE_PASSWORD_REMEMBER_DAYS', 30),
];
