<?php

declare(strict_types=1);

return [
    'title' => 'Sugerencias',
    'description' => 'Cambios sugeridos a partir de lo que hay en el sitio. Revise un plan y luego apruébelo, archívelo hasta el próximo informe o descártelo.',
    'home_stat' => 'Sugerencias abiertas',
    'generate' => 'Escribir un informe ahora',
    'generated' => '{0} El informe no tiene sugerencias.|{1} El informe tiene 1 sugerencia.|[2,*] El informe tiene :count sugerencias.',
    'period' => ':start a :end',
    'failed' => 'La fuente :source falló: :error',
    'empty' => [
        'title' => 'Aún no hay informes',
        'help' => 'Los informes se escriben según el horario de LYNX_INSIGHTS_SCHEDULE, o con lynx:insights.',
    ],
    'none' => [
        'title' => 'Nada que sugerir',
        'help' => 'El último informe no encontró cambios que sugerir.',
    ],
    'filters' => [
        'search' => 'Buscar',
        'search_placeholder' => 'Título, motivo, página',
        'state' => 'Mostrar',
        'states' => [
            'open' => 'Abiertas (:count)',
            'approved' => 'Aprobadas (:count)',
            'dismissed' => 'Descartadas (:count)',
            'archived' => 'Archivadas (:count)',
            'superseded' => 'Reemplazadas (:count)',
            'all' => 'Todas (:count)',
        ],
        'source' => 'Fuente',
        'all_sources' => 'Todas las fuentes',
        'report' => 'Informe',
        'latest' => 'Último: :start a :end',
        'clear' => 'Borrar filtros',
    ],
    'no_match' => [
        'title' => 'Ninguna sugerencia coincide',
        'help' => 'Cambie la búsqueda o los filtros, o bórrelos.',
    ],
    'pages' => '{1} 1 página|[2,*] :count páginas',
    'more_pages' => '{1} Mostrar 1 página más|[2,*] Mostrar :count páginas más',
    'state' => [
        'open' => 'Abierta',
        'approving' => 'Aprobando',
        'approved' => 'Aprobada',
        'dismissed' => 'Descartada',
        'archived' => 'Archivada',
        'superseded' => 'Reemplazada',
    ],
    'progress' => ':done de :total cambios hechos',
    'progress_label' => 'Progreso de la aprobación',
    'failed_steps' => '{1} 1 cambio falló, :done hechos. Apruebe de nuevo para repetirlo.|[2,*] :count cambios fallaron, :done hechos. Apruebe de nuevo para repetirlos.',
    'steps' => [
        'step' => 'Cambio :number',
        'pending' => 'En espera',
        'done' => 'Hecho',
        'failed' => 'Falló',
    ],
    'review' => [
        'title' => 'Aprobar hace estos cambios:',
        'open' => 'Revisar cambios',
        'close' => 'Cerrar revisión',
    ],
    'approve' => 'Aprobar',
    'archive' => 'Archivar',
    'dismiss' => 'Descartar',
    'approving' => 'Aprobación iniciada. Los cambios se hacen uno a uno, y esta página muestra el progreso.',
    'archived' => 'Sugerencia archivada. El próximo informe la trae de vuelta con nuevos datos.',
    'dismissed' => 'Sugerencia descartada.',
];
