<?php

declare(strict_types=1);

return [
    'groups' => [
        'text' => 'Texto',
        'numbers' => 'Números y fechas',
        'choices' => 'Opciones',
        'media' => 'Páginas y archivos',
        'structure' => 'Estructura',
        'other' => 'Otros',
    ],
    'text' => [
        'label' => 'Una línea',
        'description' => 'Un título, un nombre, una frase corta.',
    ],
    'textarea' => [
        'label' => 'Párrafos',
        'description' => 'Texto sin formato, texto enriquecido o Markdown.',
    ],
    'url' => [
        'label' => 'Dirección web',
        'description' => 'Un enlace a una página o a otro sitio.',
    ],
    'email' => [
        'label' => 'Dirección de correo',
        'description' => 'Se comprueba al guardar la página.',
    ],
    'integer' => [
        'label' => 'Número entero',
        'description' => 'Una cantidad, una duración, un precio en céntimos.',
    ],
    'decimal' => [
        'label' => 'Número decimal',
        'description' => 'Un precio o una valoración.',
    ],
    'date' => [
        'label' => 'Fecha',
        'description' => 'Con o sin hora.',
    ],
    'time' => [
        'label' => 'Hora del día',
        'description' => 'Como 09:30.',
    ],
    'choice' => [
        'label' => 'Opción',
        'description' => 'Una o varias opciones de una lista que defines.',
    ],
    'toggle' => [
        'label' => 'Sí o no',
        'description' => 'Activado o desactivado, como "Destacado".',
    ],
    'icon' => [
        'label' => 'Icono',
        'description' => 'Uno de los iconos de Lucide.',
    ],
    'page' => [
        'label' => 'Páginas',
        'description' => 'Enlaces a otras páginas, como cursos relacionados.',
    ],
    'page_table' => [
        'label' => 'Tabla de páginas',
        'description' => 'Páginas creadas para esta, como sesiones.',
    ],
    'images' => [
        'label' => 'Imágenes',
        'description' => 'Con puntos de enfoque y descripciones.',
    ],
    'files' => [
        'label' => 'Archivos',
        'description' => 'PDF y documentos.',
    ],
    'repeater' => [
        'label' => 'Repetidor',
        'description' => 'Una lista de elementos con los mismos campos.',
    ],
    'matrix' => [
        'label' => 'Bloques',
        'description' => 'Una página construida con bloques de distintos tipos.',
    ],
    'component' => [
        'label' => 'Componente de la aplicación',
        'description' => 'Una parte de la aplicación, como un formulario de reservas.',
    ],
];
