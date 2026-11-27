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
    'earlier' => 'Earlier reports',
    'states' => [
        'open' => '{1} 1 open|[0,*] :count open',
        'approved' => '{1} 1 approved|[0,*] :count approved',
        'dismissed' => '{1} 1 dismissed|[0,*] :count dismissed',
        'archived' => '{1} 1 archived|[0,*] :count archived',
        'superseded' => '{1} 1 replaced|[0,*] :count replaced',
    ],
    'state' => [
        'open' => 'Open',
        'approved' => 'Approved',
        'dismissed' => 'Dismissed',
        'archived' => 'Archived',
        'superseded' => 'Replaced',
    ],
    'review' => [
        'title' => 'Approving makes these changes:',
        'open' => 'Review changes',
        'close' => 'Close review',
    ],
    'approve' => 'Approve',
    'archive' => 'Archive',
    'dismiss' => 'Dismiss',
    'approved' => 'Insight approved. Its changes are saved.',
    'archived' => 'Insight archived. The next report brings it back with new evidence.',
    'dismissed' => 'Insight dismissed.',
];
