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
    'stale_drafts' => [
        'label' => 'Rascunhos parados',
        'title' => '{1} 1 rascunho não foi guardado em :days dias|[2,*] :count rascunhos não foram guardados em :days dias',
        'reason' => 'As alterações de um rascunho ficam fora da página publicada até alguém as publicar. Abra cada página para publicar o rascunho, continuar a trabalhar nele ou descartá-lo.',
        'evidence' => [
            'drafts' => 'Rascunhos não guardados no período',
            'oldest' => 'Dias desde que o mais antigo foi guardado',
        ],
    ],
    'broken_links' => [
        'label' => 'Ligações quebradas',
        'title' => '{1} 1 página publicada tem uma ligação para uma página que os visitantes não podem abrir|[2,*] :count páginas publicadas têm ligações para páginas que os visitantes não podem abrir',
        'reason' => 'Estas páginas têm :links ligações para :targets páginas não publicadas, no lixo ou eliminadas. Os visitantes que as seguem recebem uma página não encontrada. Publique a página ligada ou altere a ligação.',
        'evidence' => [
            'links' => 'Ligações para páginas que os visitantes não podem abrir',
            'targets' => 'Páginas ligadas',
        ],
    ],
];
