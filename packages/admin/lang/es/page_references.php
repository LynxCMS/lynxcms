<?php

declare(strict_types=1);

return [
    'title' => 'Referencias entre páginas',
    'description' => 'Enlaces entre páginas en campos de página y menús: enlaces que no llevan a ninguna parte, páginas sin enlaces y las páginas más enlazadas.',
    'reports' => 'Informes',
    'broken' => [
        'title' => 'Enlaces rotos',
        'intro' => 'Campos de página que apuntan a una página eliminada, en la papelera o sin publicar. Los visitantes no ven estos enlaces.',
        'empty' => 'Ningún enlace roto.',
        'missing' => 'Apunta a una página eliminada (:page)',
        'trashed' => 'Apunta a :page, en la papelera',
        'unpublished' => 'Apunta a :page, que no está publicada',
    ],
    'unlinked' => [
        'title' => 'Sin enlaces',
        'intro' => 'Páginas a las que no enlaza ningún campo de página ni menú. Los visitantes solo llegan a ellas por listas, la búsqueda o su dirección.',
        'empty' => 'Todas las páginas tienen enlaces desde otra página.',
    ],
    'linked' => [
        'title' => 'Más enlazadas',
        'intro' => 'Las páginas a las que enlazan más páginas.',
        'empty' => 'Ninguna página enlaza todavía a otra.',
        'count' => ':count página enlaza aquí|:count páginas enlazan aquí',
    ],
    'paging' => [
        'range' => ':from a :to de :total',
        'previous' => 'Anterior',
        'next' => 'Siguiente',
    ],
    'health' => [
        'title' => 'Índice',
        'intro' => 'Al guardar se mantiene al día el índice de referencias. Compruébelo después de escribir en las tablas de campos fuera de las acciones de Lynx.',
        'check' => 'Comprobar índice',
        'rebuild' => 'Reconstruir índice',
        'current' => 'El índice coincide con los valores guardados.',
        'differs' => ':count página difiere de sus valores guardados: faltan :added filas, sobran :removed, :resorted fuera de orden.|:count páginas difieren de sus valores guardados: faltan :added filas, sobran :removed, :resorted fuera de orden.',
        'queued' => 'Reconstrucción del índice iniciada.',
    ],
];
