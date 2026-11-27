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
    'earlier' => 'Informes anteriores',
    'states' => [
        'open' => '{1} 1 abierta|[0,*] :count abiertas',
        'approved' => '{1} 1 aprobada|[0,*] :count aprobadas',
        'dismissed' => '{1} 1 descartada|[0,*] :count descartadas',
        'archived' => '{1} 1 archivada|[0,*] :count archivadas',
        'superseded' => '{1} 1 reemplazada|[0,*] :count reemplazadas',
    ],
    'state' => [
        'open' => 'Abierta',
        'approved' => 'Aprobada',
        'dismissed' => 'Descartada',
        'archived' => 'Archivada',
        'superseded' => 'Reemplazada',
    ],
    'review' => [
        'title' => 'Aprobar hace estos cambios:',
        'open' => 'Revisar cambios',
        'close' => 'Cerrar revisión',
    ],
    'approve' => 'Aprobar',
    'archive' => 'Archivar',
    'dismiss' => 'Descartar',
    'approved' => 'Sugerencia aprobada. Sus cambios se guardaron.',
    'archived' => 'Sugerencia archivada. El próximo informe la trae de vuelta con nuevos datos.',
    'dismissed' => 'Sugerencia descartada.',
];
