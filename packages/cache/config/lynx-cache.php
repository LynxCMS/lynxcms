<?php

declare(strict_types=1);

return [
    'pages' => [
        // Store guest pages and serve them again until content changes (ADR-0064).
        'enabled' => env('LYNX_PAGE_CACHE', false),

        // "files" keeps HTML files on this server's disk, for nginx, Apache or Caddy to serve.
        // "store" keeps them in a Laravel cache store shared by every server, e.g. Redis on
        // Laravel Cloud, served by PHP before the session starts.
        'driver' => env('LYNX_PAGE_CACHE_DRIVER', 'files'),

        // Folder of the "files" driver. Web server rules point at it.
        'path' => public_path('lynx-cache'),

        // Settings file serve.php reads before Laravel boots; null uses bootstrap/cache/lynx-cache.php.
        'compiled' => null,

        // Cache store of the "store" driver and of the generation counter; null uses the default.
        'store' => null,

        // Hosts whose pages are stored; empty uses the host of app.url.
        'hosts' => [],

        // Seconds a page is kept; 0 keeps it until content changes.
        'lifetime' => 0,

        // A request carrying one of these cookies always goes to Laravel. lynx_bypass is set
        // while a user is signed in, lynx_page_password once a visitor unlocks a page.
        'bypass_cookies' => ['lynx_bypass', 'lynx_page_password'],

        // Dropped from the query before looking a page up; a trailing * matches a prefix.
        'ignored_parameters' => ['utm_*', 'gclid', 'fbclid', 'msclkid', 'mc_cid', 'mc_eid'],

        // Templates whose pages are stored: '*' for every template with URLs, or names with
        // rules, e.g. ['course' => ['lifetime' => 3600, 'parameters' => ['page'], 'clear' => ['self', 'parents']]].
        // "clear" names what a change to a page of this template clears: self, parents, children,
        // siblings, home, or all (the default, since menus and listings may show any page).
        'templates' => ['*' => []],

        // Pages holding the visitor's CSRF token (forms, Livewire): "refresh" stores them with a
        // placeholder that a script swaps for a fresh token after the page loads; "skip" leaves
        // them out. A cached form loses the honeypot's "sent too fast" check: forms that rely on
        // it belong in an island, or their template can say "skip". A template rule's "csrf"
        // key overrides this.
        'csrf' => env('LYNX_PAGE_CACHE_CSRF', 'refresh'),

        // Query variants stored per page, so random parameters cannot fill the disk.
        'max_variants' => 50,

        // Seconds an edge cache (Laravel Cloud, Cloudflare) may keep a served page; 0 sends no
        // public Cache-Control header. The edge cannot be cleared per page, so keep it short.
        'edge_ttl' => 0,

        // Adds X-Lynx-Cache headers, with the reason a page was not stored.
        'debug' => env('LYNX_PAGE_CACHE_DEBUG', false),
    ],

    'fragments' => [
        // lynx()->cache()->remember() and @lynxCache keep what they wrap (ADR-0064).
        'enabled' => env('LYNX_FRAGMENT_CACHE', true),

        // Cache store for fragments; null uses the default store.
        'store' => null,

        // Seconds a fragment is kept when it sets none; null keeps it until what it depends on changes.
        'ttl' => null,

        // Guests carrying these cookies get their own copies, since they may see more pages.
        'vary_cookies' => ['lynx_page_password'],
    ],
];
