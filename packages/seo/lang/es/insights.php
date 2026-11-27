<?php

declare(strict_types=1);

return [
    'missing_descriptions' => [
        'label' => 'Descripciones que faltan',
        'title' => '{1} 1 página no tiene descripción para los buscadores|[2,*] :count páginas no tienen descripción para los buscadores',
        'reason' => 'Los resultados de búsqueda y los enlaces compartidos muestran la descripción predeterminada del sitio para estas páginas, o ninguna. Escriba una descripción en la pestaña SEO de cada página, empezando por las más enlazadas.',
        'evidence' => [
            'pages' => 'Páginas sin descripción',
            'links' => 'Enlaces a las páginas listadas',
        ],
    ],
];
