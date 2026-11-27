<?php

declare(strict_types=1);

return [
    'title' => 'Insights',
    'description' => 'Changes suggested from what is on the site. Review a plan, then approve it, archive it until the next report, or dismiss it.',
    'home_stat' => 'Open insights',
    'generate' => 'Write a report now',
    'generated' => '{0} The report has no insights.|{1} The report has 1 insight.|[2,*] The report has :count insights.',
    'period' => ':start to :end',
    'failed' => 'The :source source failed: :error',
    'empty' => [
        'title' => 'No reports yet',
        'help' => 'Reports are written on the schedule set in LYNX_INSIGHTS_SCHEDULE, or with lynx:insights.',
    ],
    'none' => [
        'title' => 'Nothing to suggest',
        'help' => 'The latest report found no changes to suggest.',
    ],
    'filters' => [
        'search' => 'Search',
        'search_placeholder' => 'Title, reason, page',
        'state' => 'Show',
        'states' => [
            'open' => 'Open (:count)',
            'approved' => 'Approved (:count)',
            'dismissed' => 'Dismissed (:count)',
            'archived' => 'Archived (:count)',
            'superseded' => 'Replaced (:count)',
            'all' => 'All (:count)',
        ],
        'source' => 'Source',
        'all_sources' => 'All sources',
        'report' => 'Report',
        'latest' => 'Latest: :start to :end',
        'clear' => 'Clear filters',
    ],
    'no_match' => [
        'title' => 'No insights match',
        'help' => 'Change the search or the filters, or clear them.',
    ],
    'pages' => '{1} 1 page|[2,*] :count pages',
    'more_pages' => '{1} Show 1 more page|[2,*] Show :count more pages',
    'state' => [
        'open' => 'Open',
        'approving' => 'Approving',
        'approved' => 'Approved',
        'dismissed' => 'Dismissed',
        'archived' => 'Archived',
        'superseded' => 'Replaced',
    ],
    'progress' => ':done of :total changes made',
    'progress_label' => 'Approval progress',
    'failed_steps' => '{1} 1 change failed, :done made. Approve again to retry it.|[2,*] :count changes failed, :done made. Approve again to retry them.',
    'failed_step' => 'Change :number: :error',
    'review' => [
        'title' => 'Approving makes these changes:',
        'open' => 'Review changes',
        'close' => 'Close review',
    ],
    'approve' => 'Approve',
    'archive' => 'Archive',
    'dismiss' => 'Dismiss',
    'approving' => 'Approval started. The changes are made one at a time, and this page shows how far they got.',
    'archived' => 'Insight archived. The next report brings it back with new evidence.',
    'dismissed' => 'Insight dismissed.',
];
