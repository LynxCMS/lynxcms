<?php

declare(strict_types=1);

return [
    'title' => 'Contraseñas de página',
    'nav' => 'Contraseñas de página',
    'description' => 'Páginas que los visitantes solo abren con una contraseña, compartida con las páginas que tienen debajo.',
    'kind' => [
        'label' => 'Contraseña de página',
        'description' => 'Una contraseña que los visitantes escriben para abrir la página.',
    ],
    'protected' => 'Protegida con contraseña',
    'protected_by' => 'Protegida por :page',
    'templates' => 'Plantillas',
    'templates_help' => 'Las páginas de estas plantillas tienen un campo Contraseña en la barra lateral del editor de páginas. Si desactivas una plantilla, las contraseñas se conservan, pero sus páginas pasan a ser públicas.',
    'turn_off' => 'Desactivar',
    'turn_on' => 'Activar',
    'on' => 'Activada',
    'off' => 'Desactivada',
    'pages' => 'Páginas protegidas',
    'empty' => 'Ninguna página tiene contraseña todavía.',
    'empty_help' => 'Activa una plantilla arriba y luego define una contraseña en el editor de una página.',
    'below' => '{0} Ninguna página debajo|{1} 1 página debajo|[2,*] :count páginas debajo',
    'set' => 'Definida',
    'not_set' => 'Sin definir',
    'placeholder' => 'Sin contraseña: la página es pública',
    'hide' => 'Ocultar',
    'show' => 'Mostrar',
    'generate' => 'Generar',
];
