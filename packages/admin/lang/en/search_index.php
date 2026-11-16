<?php

declare(strict_types=1);

return [
    'title' => 'Search index',
    'description' => 'The words search finds pages by: the title, and the fields each template marks searchable.',
    'denied' => 'You may not manage the search index.',
    'rebuilt' => '{0} No pages to index.|{1} Indexed 1 page.|[2,*] Indexed :count pages.',
    'tabs' => [
        'overview' => 'Overview',
        'tools' => 'Tools',
    ],
    'rebuild' => [
        'title' => 'Rebuild',
        'hint' => 'Saving a page keeps its words current. Rebuild after changing which fields are searchable or a field\'s settings, such as a choice label. Large sites can rebuild one template at a time.',
        'template' => 'Templates',
        'system_template' => 'System templates',
        'no_system_template' => 'None',
        'every_page' => 'Every page',
        'button' => 'Rebuild the index',
    ],
    'languages' => [
        'title' => 'Languages',
        'language' => 'Language',
        'indexed' => 'In the index',
        'count' => ':rows of :pages pages',
        'own' => 'Own text',
        'own_shared' => 'Shared titles',
        'fallback_hint' => 'Every page has a row in every language. Own text counts the pages with their own title in that language; the others are indexed with the text they fall back to, so visitors find them in that language too.',
        'incomplete' => 'Needs a rebuild',
        'plurals' => 'Singular and plural',
        'plurals_on' => 'Found from either',
        'plurals_off' => 'Exact words only',
    ],
    'templates' => [
        'title' => 'What each template indexes',
        'hint' => 'Fields marked searchable whose type gives text, such as text, textarea and choices. Pages without a URL are indexed too; the site\'s search shows them only if it asks for them.',
        'template' => 'Template',
        'pages' => 'Pages',
        'fields' => 'Indexed fields',
        'system' => 'System templates',
        'system_hint' => 'Templates that Lynx and its modules keep for themselves, such as the trash or settings pages.',
        'owner' => 'Belongs to',
        'no_url' => 'No URL',
    ],
    'lookup' => [
        'title' => 'Test Indexed Words',
        'hint' => 'Look up a page to see the words search finds it by, in each language.',
        'page' => 'Page path or id',
        'placeholder' => 'For example /about or 42',
        'missing' => 'There is no page at that path or id.',
        'not_indexed' => 'This page has no words in the index yet. Rebuild the index.',
    ],
];
