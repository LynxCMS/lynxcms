<?php

declare(strict_types=1);

return [
    'denied' => [
        'anywhere' => 'No tienes el permiso [:permission] (:label).',
        'template' => 'No tienes el permiso [:permission] (:label) para la plantilla [:template].',
        'page' => 'No tienes el permiso [:permission] (:label) en la página [:page].',
        'action' => 'No tienes el permiso [:permissions], que [:action] necesita.',
        'superusers' => 'Solo los superusuarios pueden ejecutar [:action].',
    ],
    'last_superuser' => 'Esto quitaría al último superusuario. Da primero un rol de superusuario a otra persona.',
    'schema_editing' => '[:action] cambia el esquema, y la edición del esquema está desactivada en este sitio (lynx.schema_editing): el esquema viene de archivos. Cambia lynx/schema y ejecuta php artisan lynx:schema:apply.',
    'agent_languages' => 'Este agente no puede escribir en: :languages.',
    'providers' => [
        'unknown' => 'No hay ningún proveedor de traducción con el nombre [:provider].',
        'none' => 'No hay ningún proveedor de traducción configurado. Instala lynxcms/ai o un módulo de traducción.',
    ],
    'roles' => [
        'missing' => 'El rol [:role] no existe.',
        'unknown' => 'Rol desconocido: [:roles].',
        'needs' => '[:permission] necesita [:permissions] en el mismo rol.',
        'unknown_permission' => 'Permiso desconocido: [:permission].',
        'not_scoped' => '[:permission] no se puede limitar a plantillas ni ramas.',
        'unknown_template' => 'Plantilla desconocida: [:templates].',
        'unknown_page' => 'Página desconocida: [:pages].',
    ],
];
