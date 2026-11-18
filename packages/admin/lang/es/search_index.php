<?php

declare(strict_types=1);

return [
    'title' => 'Índice de búsqueda',
    'description' => 'Las palabras por las que la búsqueda encuentra las páginas: el título y los campos que cada plantilla marca como buscables.',
    'denied' => 'No puedes gestionar el índice de búsqueda.',
    'rebuilt' => '{0} No hay páginas que indexar.|{1} Se ha indexado 1 página.|[2,*] Se han indexado :count páginas.',
    'tabs' => [
        'overview' => 'Resumen',
        'tools' => 'Herramientas',
    ],
    'rebuild' => [
        'title' => 'Regenerar',
        'hint' => 'Al guardar una página, sus palabras se mantienen al día. Regenera el índice después de cambiar qué campos son buscables o los ajustes de un campo, como la etiqueta de una opción. Los sitios grandes pueden regenerar una plantilla cada vez.',
        'template' => 'Plantillas',
        'system_template' => 'Plantillas del sistema',
        'no_system_template' => 'Ninguna',
        'every_page' => 'Todas las páginas',
        'button' => 'Regenerar el índice',
    ],
    'languages' => [
        'title' => 'Idiomas',
        'language' => 'Idioma',
        'indexed' => 'En el índice',
        'count' => ':rows de :pages páginas',
        'own' => 'Texto propio',
        'own_shared' => 'Títulos compartidos',
        'fallback_hint' => 'Cada página tiene una fila en cada idioma. Texto propio cuenta las páginas con su propio título en ese idioma; las demás se indexan con el texto de su idioma de respaldo, para que los visitantes también las encuentren en ese idioma.',
        'incomplete' => 'Hay que regenerarlo',
        'plurals' => 'Singular y plural',
        'plurals_on' => 'Se encuentra por cualquiera de los dos',
        'plurals_off' => 'Solo palabras exactas',
    ],
    'templates' => [
        'title' => 'Qué indexa cada plantilla',
        'hint' => 'Los campos marcados como buscables cuyo tipo da texto, como texto, área de texto y opciones. Las páginas sin URL también se indexan; la búsqueda del sitio solo las muestra si las pide.',
        'template' => 'Plantilla',
        'pages' => 'Páginas',
        'fields' => 'Campos indexados',
        'system' => 'Plantillas del sistema',
        'system_hint' => 'Plantillas que Lynx y sus módulos reservan para sí, como la papelera o las páginas de ajustes.',
        'owner' => 'Pertenece a',
        'no_url' => 'Sin URL',
    ],
    'lookup' => [
        'title' => 'Probar las palabras indexadas',
        'hint' => 'Busca una página para ver las palabras por las que la encuentra la búsqueda, en cada idioma.',
        'page' => 'Ruta o id de la página',
        'placeholder' => 'Por ejemplo /about o 42',
        'missing' => 'No hay ninguna página con esa ruta o id.',
        'not_indexed' => 'Esta página aún no tiene palabras en el índice. Regenera el índice.',
    ],
];
