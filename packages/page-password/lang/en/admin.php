<?php

declare(strict_types=1);

return [
    'title' => 'Page passwords',
    'nav' => 'Page passwords',
    'description' => 'Pages visitors open only with a password, shared by the pages below them.',
    'kind' => [
        'label' => 'Page password',
        'description' => 'A password visitors type to open the page.',
    ],
    'protected' => 'Password protected',
    'protected_by' => 'Protected by :page',
    'templates' => 'Templates',
    'templates_help' => 'Pages of these templates have a Password field in the page editor\'s sidebar. Turning a template off keeps the passwords, but its pages become public.',
    'turn_off' => 'Turn off',
    'turn_on' => 'Turn on',
    'pages' => 'Protected pages',
    'empty' => 'No page has a password yet.',
    'below' => '{0} No pages below|{1} 1 page below|[2,*] :count pages below',
    'set' => 'Set',
    'not_set' => 'Not set',
    'placeholder' => 'No password: the page is public',
    'hide' => 'Hide',
    'show' => 'Show',
    'generate' => 'Generate',
];
