<?php

declare(strict_types=1);

return [
    'missing_translations' => [
        'label' => 'Traducciones pendientes',
        'title' => '{1} 1 página no tiene versión en :language|[2,*] :count páginas no tienen versión en :language',
        'reason' => 'Quien lee en :language ve estas páginas en el idioma de respaldo. El plan traduce las :shown más enlazadas a sus borradores, para revisarlas antes de publicar.',
        'evidence' => [
            'pages' => 'Páginas sin versión en :language',
            'links' => 'Enlaces a las páginas del plan',
        ],
    ],
    'stale_drafts' => [
        'label' => 'Borradores parados',
        'title' => '{1} 1 borrador no se guardó en :days días|[2,*] :count borradores no se guardaron en :days días',
        'reason' => 'Los cambios de un borrador quedan fuera de la página publicada hasta que alguien los publique. Abra cada página para publicar su borrador, seguir trabajando en él o descartarlo.',
        'evidence' => [
            'drafts' => 'Borradores no guardados en el período',
            'oldest' => 'Días desde que se guardó el más antiguo',
        ],
    ],
    'broken_links' => [
        'label' => 'Enlaces rotos',
        'title' => '{1} 1 página publicada enlaza a una página que los visitantes no pueden abrir|[2,*] :count páginas publicadas enlazan a páginas que los visitantes no pueden abrir',
        'reason' => 'Estas páginas tienen :links enlaces a :targets páginas no publicadas, en la papelera o eliminadas. Los visitantes que los siguen reciben una página no encontrada. Publique la página enlazada o cambie el enlace.',
        'evidence' => [
            'links' => 'Enlaces a páginas que los visitantes no pueden abrir',
            'targets' => 'Páginas enlazadas',
        ],
    ],
];
