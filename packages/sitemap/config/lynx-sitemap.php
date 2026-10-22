<?php

declare(strict_types=1);

return [
    // Serve /sitemap.xml and its parts.
    'enabled' => env('LYNX_SITEMAP', true),

    // URLs per file before the sitemap splits into parts under an index. Editors can change it in
    // the admin. The protocol allows 50000 at most.
    'per_file' => 10000,

    // "none" for one list of parts over every page, "template" for one sitemap per template.
    'group_by' => 'none',

    // Hidden pages are reachable by URL but kept out of navigation.
    'include_hidden' => false,

    // Templates whose pages never appear, e.g. ['search-results']. Editors can add more in the admin.
    'exclude_templates' => [],

    'cache' => [
        // A cache store name; null uses the default store.
        'store' => null,
        // Seconds a built sitemap is kept. Any content change drops it sooner.
        'ttl' => 86400,
    ],
];
