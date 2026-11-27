<?php

declare(strict_types=1);

return [
    'missing_translations' => [
        'label' => 'Missing translations',
        'title' => '{1} 1 page has no :language version|[2,*] :count pages have no :language version',
        'reason' => 'Visitors who read :language see these pages in the fallback language. The plan translates the :shown most linked ones into their drafts, for review before publishing.',
        'evidence' => [
            'pages' => 'Pages without a :language version',
            'links' => 'Links to the pages in the plan',
        ],
    ],
];
