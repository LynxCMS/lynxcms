<?php

declare(strict_types=1);

return [
    'or' => ' or ',
    'page' => [
        'missing' => 'Page [:page] does not exist.',
        'ambiguous' => 'Several pages are named [:name]; give the page\'s path or id.',
        'being_edited' => 'Page [:page] is being edited by :holder until :time.',
        'being_edited_retry' => 'Page [:page] is being edited by :holder until :time. Try again when they are done.',
        'conflict' => 'Page [:page] was saved by :holder at :time after you loaded it. Reload it to see those changes; your edits were not saved.',
        'locked' => 'Page [:page] is locked. Unlock it first (pages.status with remove ["locked"]).',
        'switch_template' => 'Use pages.switch_template to change a page\'s template.',
        'template_required' => 'A template is required for new pages.',
        'system' => [
            'moved' => 'Page [:page] is a system page that the CMS or a module depends on; it cannot be moved.',
            'switched' => 'Page [:page] is a system page that the CMS or a module depends on; it cannot be switched to another template.',
            'status' => 'Page [:page] is a system page that the CMS or a module depends on; it cannot be hidden, unpublished or locked.',
        ],
        'delete' => [
            'locked' => 'Page [:page] is locked. Unlock it first.',
            'locked_below' => 'Page [:page] below [:above] is locked. Unlock it first.',
            'system' => 'Page [:page] is a system page that the CMS or a module depends on; it cannot be deleted.',
            'system_below' => 'Page [:page] below [:above] is a system page that the CMS or a module depends on; it cannot be deleted.',
        ],
    ],
    'parent' => [
        'required' => 'A parent page is required.',
        'missing' => 'Parent page [:page] does not exist.',
        'trash' => 'Pages cannot be created in the trash.',
        'reference' => 'The parent must be a page id or path.',
        'no_path' => 'No page at path [:path].',
    ],
    'template' => [
        'missing' => 'Template [:template] does not exist.',
        'singleton' => 'Template [:template] is a singleton and already has a page.',
    ],
    'language' => [
        'missing' => 'There is no language [:language].',
    ],
    'names' => [
        'empty' => 'The page name cannot be empty.',
        'required' => 'A name in the default language is required.',
        'required_or_title' => 'A name or a title in the default language is required.',
        'taken' => 'Another page under the same parent already uses the name [:name].',
        'unique_taken' => 'The name [:name] belongs to the unique page :page; choose another name.',
        'unique_used' => 'A unique page\'s name must not be used anywhere else, but [:name] is already used by :page.',
    ],
    'values' => [
        'required' => ':field is required.',
        'required_default' => ':field is required in the default language.',
        'required_after_saving' => ':field is required. Create the page unpublished, add it, then publish.',
        'no_field' => 'This template has no field [:field].',
        'template_no_field' => 'Template [:template] has no field [:field].',
        'not_translatable' => 'Field [:field] is not translatable: give one value, not one per language.',
        'not_an_option' => '[:values] is not one of the options: :options.',
        'component' => 'Component [:component] is not registered or not allowed here.',
        'url' => 'Enter an address starting with https://.',
        'url_or_path' => 'Enter an address starting with https:// or a path starting with /.',
        'page_template' => 'Pick pages that use :templates: [:page] uses :template.',
        'pages_max' => 'Pick at most :count page.|Pick at most :count pages.',
        'pages_min' => 'Pick at least :count page.|Pick at least :count pages.',
    ],
    'items' => [
        'list' => 'The items must be a list.',
        'key' => 'An item key is a ULID: leave it out for new items.',
        'max' => 'There may be at most :count item.|There may be at most :count items.',
        'min' => 'There must be at least :count item.|There must be at least :count items.',
        'type_required' => 'Every block needs a type: :types.',
        'no_type' => 'There is no block type [:type]. Use one of: :types.',
        'type_not_allowed' => 'Blocks of type [:type] are not allowed here. Allowed: :types.',
    ],
];
