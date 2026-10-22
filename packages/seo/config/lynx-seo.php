<?php

declare(strict_types=1);

return [
    // How page titles read when a page sets no SEO title. Editors can change it per language in
    // the SEO settings. {title} is the page title, {site} the site name.
    'title_pattern' => '{title} | {site}',

    // Fields read, in order, for a description when a page sets none. Tags are stripped.
    'description_fields' => ['summary', 'body'],

    // Longer descriptions are cut at a word, in characters.
    'description_length' => 160,

    // Image fields read, in order, for the share image when a page sets none (needs lynxcms/media).
    'image_fields' => [],

    // Hidden pages are reachable by URL but kept out of navigation: keep them out of search too.
    'noindex_hidden' => true,

    // Outside production every page says noindex and robots.txt disallows everything, so a
    // staging copy never lands in search results.
    'block_non_production' => env('LYNX_SEO_BLOCK_NON_PRODUCTION', true),

    // Pages the admin's SEO audit checks at most, oldest first.
    'audit_limit' => 2000,

    'robots' => [
        // Serve /robots.txt. A real public/robots.txt file is served by the web server first.
        'enabled' => true,
    ],
];
