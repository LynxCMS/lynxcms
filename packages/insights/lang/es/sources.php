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
];
