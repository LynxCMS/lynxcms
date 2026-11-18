<?php

declare(strict_types=1);

return [
    'title' => 'Roles',
    'description' => 'Qué puede hacer cada rol, en qué plantillas y en qué ramas.',
    'new' => 'Nuevo rol',
    'heading' => 'Rol :name',
    'denied' => 'Solo los superusuarios gestionan los roles.',
    'saved' => 'Guardado.',
    'form' => [
        'heading' => 'Rol',
        'name' => 'Nombre',
        'name_help' => 'Letras minúsculas, dígitos, guiones y guiones bajos, por ejemplo course-editor.',
        'label' => 'Etiqueta',
        'label_placeholder' => 'A partir del nombre',
        'superuser' => 'Superusuario: puede hacerlo todo, incluso gestionar los roles',
        'permissions' => 'Permisos',
        'permissions_help' => 'Los permisos de página se pueden limitar a plantillas y a ramas del árbol de páginas (una página y todo lo que tiene debajo). Si no se elige nada, se aplican en todas partes.',
        'only_templates' => 'Solo estas plantillas',
        'only_under' => 'Solo dentro de estas páginas',
        'search' => 'Buscar una página',
        'create' => 'Crear rol',
        'save' => 'Guardar',
        'back' => 'Volver a los roles',
        'delete' => 'Eliminar rol',
        'confirm' => '¿Eliminar :name? 1 usuario lo pierde.|¿Eliminar :name? :count usuarios lo pierden.',
        'delete_named' => 'Eliminar el rol :name',
        'cancel' => 'Cancelar',
    ],
    'list' => [
        'name' => 'Nombre',
        'label' => 'Etiqueta',
        'grants' => 'Permisos',
        'users' => 'Usuarios',
        'superuser' => 'superusuario',
        'all' => 'Todos',
        'empty' => 'Aún no hay roles.',
        'empty_help' => 'Mientras ningún usuario tenga un rol, cualquier usuario con sesión iniciada puede hacerlo todo. Un rol dice qué pueden hacer sus usuarios y dónde.',
    ],
    'permissions' => [
        'menus' => [
            'manage' => 'Gestionar los menús',
        ],
        'forms' => [
            'view' => 'Ver y clasificar los envíos de formularios',
            'manage' => 'Crear formularios y eliminar envíos',
        ],
        'seo' => [
            'manage' => 'Gestionar los ajustes de SEO',
        ],
        'sitemap' => [
            'manage' => 'Gestionar el mapa del sitio',
        ],
        'page_cache' => [
            'manage' => 'Gestionar la caché de páginas',
        ],
        'static' => [
            'export' => 'Exportar el sitio como archivos estáticos',
        ],
        'admin' => [
            'access' => 'Abrir el panel',
        ],
        'pages' => [
            'view' => 'Ver las páginas y el contenido sin publicar en el panel y en las herramientas de lectura del agente',
            'edit' => 'Editar páginas existentes, traducirlas y eliminar URL antiguas',
            'create' => 'Crear páginas',
            'publish' => 'Publicar y despublicar páginas',
            'status' => 'Ocultar, volver a mostrar, bloquear y desbloquear páginas',
            'move' => 'Mover y ordenar páginas',
            'delete' => 'Eliminar páginas',
            'template' => 'Cambiar la plantilla de las páginas',
        ],
        'schema' => [
            'manage' => 'Gestionar campos, plantillas e idiomas',
        ],
        'agent' => [
            'use' => 'Usar el agente integrado y aprobar sus planes',
        ],
        'system' => [
            'manage' => 'Cambiar las páginas del sistema (por ejemplo, al desinstalar un módulo)',
        ],
    ],
];
