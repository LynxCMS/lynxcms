<?php

declare(strict_types=1);

return [
    /*
    | Where files sent with forms are stored. Keep it private: the admin serves them to users who
    | may see submissions.
    */
    'disk' => env('LYNX_FORMS_DISK', 'local'),

    /*
    | Submissions per form from one visitor (hashed IP) in the window, before "too many attempts".
    */
    'rate_limit' => [
        'attempts' => (int) env('LYNX_FORMS_RATE_ATTEMPTS', 5),
        'decay_seconds' => (int) env('LYNX_FORMS_RATE_DECAY', 600),
    ],

    /*
    | Addresses mailed about submissions when no user holds forms.view and the form names none.
    */
    'notify' => array_filter(explode(',', (string) env('LYNX_FORMS_NOTIFY', ''))),

    /*
    | lynx:forms:purge deletes spam and archived submissions older than this.
    */
    'purge_after_days' => (int) env('LYNX_FORMS_PURGE_DAYS', 180),

    /*
    | Days a submission is kept in any status before lynx:forms:purge deletes it with its files,
    | for forms that set none of their own. Null keeps them.
    */
    'retention_days' => env('LYNX_FORMS_RETENTION_DAYS'),
];
