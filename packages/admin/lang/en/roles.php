<?php

declare(strict_types=1);

return [
    'title' => 'Roles',
    'description' => 'What each role may do, on which templates and branches.',
    'new' => 'New role',
    'heading' => 'Role :name',
    'denied' => 'Only superusers manage roles.',
    'saved' => 'Saved.',
    'form' => [
        'heading' => 'Role',
        'name' => 'Name',
        'name_help' => 'Lowercase letters, digits, dashes and underscores, e.g. course-editor.',
        'label' => 'Label',
        'label_placeholder' => 'From the name',
        'superuser' => 'Superuser: may do everything, including managing roles',
        'permissions' => 'Permissions',
        'permissions_help' => 'Page permissions can be limited to templates and to branches of the page tree (a page and everything below it). Nothing chosen means everywhere.',
        'only_templates' => 'Only these templates',
        'only_under' => 'Only under these pages',
        'search' => 'Search for a page',
        'create' => 'Create role',
        'save' => 'Save',
        'back' => 'Back to roles',
        'delete' => 'Delete role',
        'confirm' => 'Delete :name? 1 user loses it.|Delete :name? :count users lose it.',
        'delete_named' => 'Delete role :name',
        'cancel' => 'Cancel',
    ],
    'list' => [
        'name' => 'Name',
        'label' => 'Label',
        'grants' => 'Grants',
        'users' => 'Users',
        'superuser' => 'superuser',
        'all' => 'All',
        'empty' => 'No roles yet.',
        'empty_help' => 'Until a user has a role, every signed in user may do everything. A role says what its users may do, and where.',
    ],
];
