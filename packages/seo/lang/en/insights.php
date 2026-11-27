<?php

declare(strict_types=1);

return [
    'missing_descriptions' => [
        'label' => 'Missing descriptions',
        'title' => '{1} 1 page has no description for search engines|[2,*] :count pages have no description for search engines',
        'reason' => 'Search results and shared links show the site\'s default description for these pages, or none. Write a description in the SEO tab of each page, starting with the most linked.',
        'evidence' => [
            'pages' => 'Pages without a description',
            'links' => 'Links to the pages listed',
        ],
    ],
];
