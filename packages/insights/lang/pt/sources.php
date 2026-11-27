<?php

declare(strict_types=1);

return [
    'missing_translations' => [
        'label' => 'Traduções em falta',
        'title' => '{1} 1 página não tem versão em :language|[2,*] :count páginas não têm versão em :language',
        'reason' => 'Quem lê em :language vê estas páginas na língua de recurso. O plano traduz as :shown mais ligadas para os seus rascunhos, para rever antes de publicar.',
        'evidence' => [
            'pages' => 'Páginas sem versão em :language',
            'links' => 'Ligações para as páginas do plano',
        ],
    ],
];
