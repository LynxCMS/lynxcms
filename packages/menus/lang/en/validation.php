<?php

declare(strict_types=1);

return [
    'menu' => [
        'taken' => 'A menu named [:menu] already exists.',
        'title' => 'A title in the default language is required.',
        'missing' => 'There is no menu [:menu].',
        'not_found' => 'Menu [:menu] does not exist.',
    ],
    'field' => 'Field [:field] exists with other settings; the menu builder needs [:needs].',
    'item' => [
        'missing' => 'Menu item [:item] does not exist.',
        'other_menu' => 'Menu item [:item] is not in menu [:menu].',
        'parent' => 'The parent must be this menu or one of its items.',
        'label' => 'A label in the default language is required.',
        'no_language' => 'Show the item in at least one language.',
        'page' => 'Choose an existing page that has a URL.',
        'url' => 'A URL in the default language is required.',
        'url_scheme' => 'The :language URL must be relative, a #fragment, or use :schemes.',
        'target' => 'Use _blank, _self, _parent, _top or a frame name (letters, digits, - and _).',
    ],
    'languages' => 'Unknown language [:languages].',
];
