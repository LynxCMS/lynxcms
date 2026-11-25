<?php

declare(strict_types=1);

return [
    'denied' => [
        'anywhere' => 'You do not have permission [:permission] (:label).',
        'template' => 'You do not have permission [:permission] (:label) for template [:template].',
        'page' => 'You do not have permission [:permission] (:label) on page [:page].',
        'action' => 'You do not have permission [:permissions] needed by [:action].',
        'superusers' => 'Only superusers may run [:action].',
        'field_protection' => 'Only superusers may change the [:setting] setting of field [:field].',
    ],
    'last_superuser' => 'This would remove the last superuser. Give another user a superuser role first.',
    'schema_editing' => '[:action] changes the schema, and schema editing is turned off on this site (lynx.schema_editing): its schema comes from files. Change lynx/schema and run php artisan lynx:schema:apply.',
    'agent_languages' => 'This agent may not write in: :languages.',
    'providers' => [
        'unknown' => 'No translation provider is named [:provider].',
        'none' => 'No translation provider is configured. Install lynxcms/ai or a translation module.',
    ],
    'roles' => [
        'missing' => 'Role [:role] does not exist.',
        'unknown' => 'Unknown role [:roles].',
        'needs' => '[:permission] needs [:permissions] in the same role.',
        'unknown_permission' => 'Unknown permission [:permission].',
        'not_scoped' => '[:permission] cannot be limited to templates or branches.',
        'unknown_template' => 'Unknown template [:templates].',
        'unknown_page' => 'Unknown page [:pages].',
    ],
];
