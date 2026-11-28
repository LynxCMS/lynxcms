<?php

declare(strict_types=1);

return [
    'missing_translations' => [
        'label' => 'Missing translations',
        'title' => '{1} 1 page has no :language version|[2,*] :count pages have no :language version',
        'reason' => 'Visitors who read :language see these pages in the fallback language. The plan translates the :shown most linked ones into their drafts, for review before publishing.',
        'reason_views' => 'Visitors who read :language see these pages in the fallback language. The plan translates the :shown most viewed ones into their drafts, for review before publishing.',
        'evidence' => [
            'pages' => 'Pages without a :language version',
            'links' => 'Links to the pages in the plan',
            'views' => 'Views of the pages in the plan, last :days days',
        ],
    ],
    'stale_drafts' => [
        'label' => 'Stale drafts',
        'title' => '{1} 1 draft was not saved in :days days|[2,*] :count drafts were not saved in :days days',
        'reason' => 'Changes in a draft stay out of the live page until someone publishes them. Open each page to publish its draft, keep working on it, or discard it.',
        'evidence' => [
            'drafts' => 'Drafts not saved in the period',
            'oldest' => 'Days since the oldest was saved',
        ],
    ],
    'broken_links' => [
        'label' => 'Broken links',
        'title' => '{1} 1 published page links to a page visitors cannot open|[2,*] :count published pages link to pages visitors cannot open',
        'reason' => 'These pages have :links links to :targets pages that are unpublished, in the trash, or deleted. Visitors who follow them get a page not found. Publish the linked page or change the link.',
        'evidence' => [
            'links' => 'Links to pages visitors cannot open',
            'targets' => 'Pages linked to',
        ],
    ],
    'neglected_pages' => [
        'label' => 'Neglected popular pages',
        'title' => '{1} 1 popular page was not changed in :months months|[2,*] :count popular pages were not changed in :months months',
        'reason' => 'These pages are among the most viewed of the last :days days, and nobody changed them in :months months. Check that what they say is still true.',
        'evidence' => [
            'views' => 'Views in the last :days days',
            'months' => 'Months without a change',
        ],
    ],
];
