<?php

declare(strict_types=1);

return [
    'missing_descriptions' => [
        'label' => 'Descrições em falta',
        'title' => '{1} 1 página não tem descrição para os motores de pesquisa|[2,*] :count páginas não têm descrição para os motores de pesquisa',
        'reason' => 'Os resultados de pesquisa e as ligações partilhadas mostram a descrição predefinida do site para estas páginas, ou nenhuma. Escreva uma descrição no separador SEO de cada página, a começar pelas mais ligadas.',
        'evidence' => [
            'pages' => 'Páginas sem descrição',
            'links' => 'Ligações para as páginas listadas',
        ],
    ],
];
