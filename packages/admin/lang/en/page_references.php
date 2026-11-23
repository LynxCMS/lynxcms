<?php

declare(strict_types=1);

return [
    'title' => 'Page references',
    'description' => 'Links between pages in page fields and menus: links that lead nowhere, pages nothing links to, and the most linked pages.',
    'reports' => 'Reports',
    'broken' => [
        'title' => 'Broken links',
        'intro' => 'Page fields that point at a page that was deleted, is in the trash, or is unpublished. Visitors don\'t see these links.',
        'empty' => 'No broken links.',
        'missing' => 'Points at a deleted page (:page)',
        'trashed' => 'Points at :page, in the trash',
        'unpublished' => 'Points at :page, which is unpublished',
    ],
    'unlinked' => [
        'title' => 'Not linked',
        'intro' => 'Pages that no page field or menu links to. Visitors reach them only through lists, search, or their address.',
        'empty' => 'Every page is linked from another page.',
    ],
    'linked' => [
        'title' => 'Most linked',
        'intro' => 'The pages that the most pages link to.',
        'empty' => 'No page links to another page yet.',
        'count' => ':count page links here|:count pages link here',
    ],
    'paging' => [
        'range' => ':from to :to of :total',
        'previous' => 'Previous',
        'next' => 'Next',
    ],
    'health' => [
        'title' => 'Index',
        'intro' => 'Saves keep the index of page references current. Check it after writing field tables outside Lynx\'s actions.',
        'check' => 'Check index',
        'rebuild' => 'Rebuild index',
        'current' => 'The index matches the stored values.',
        'differs' => ':count page differs from its stored values: :added rows missing, :removed extra, :resorted out of order.|:count pages differ from their stored values: :added rows missing, :removed extra, :resorted out of order.',
        'queued' => 'Index rebuild started.',
    ],
];
