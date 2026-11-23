<?php

declare(strict_types=1);

return [
    'title' => 'Referências entre páginas',
    'description' => 'Ligações entre páginas em campos de página e menus: ligações que não levam a lado nenhum, páginas sem ligações e as páginas mais ligadas.',
    'reports' => 'Relatórios',
    'broken' => [
        'title' => 'Ligações quebradas',
        'intro' => 'Campos de página que apontam para uma página apagada, no lixo ou não publicada. Os visitantes não veem estas ligações.',
        'empty' => 'Nenhuma ligação quebrada.',
        'missing' => 'Aponta para uma página apagada (:page)',
        'trashed' => 'Aponta para :page, no lixo',
        'unpublished' => 'Aponta para :page, que não está publicada',
    ],
    'unlinked' => [
        'title' => 'Sem ligações',
        'intro' => 'Páginas para as quais nenhum campo de página nem menu liga. Os visitantes só lá chegam por listas, pela pesquisa ou pelo endereço.',
        'empty' => 'Todas as páginas têm ligações de outra página.',
    ],
    'linked' => [
        'title' => 'Mais ligadas',
        'intro' => 'As páginas para as quais mais páginas ligam.',
        'empty' => 'Nenhuma página liga ainda para outra.',
        'count' => ':count página liga para aqui|:count páginas ligam para aqui',
    ],
    'paging' => [
        'range' => ':from a :to de :total',
        'previous' => 'Anterior',
        'next' => 'Seguinte',
    ],
    'health' => [
        'title' => 'Índice',
        'intro' => 'Cada gravação mantém o índice de referências atualizado. Verifique-o depois de escrever nas tabelas dos campos fora das ações do Lynx.',
        'check' => 'Verificar índice',
        'rebuild' => 'Reconstruir índice',
        'current' => 'O índice corresponde aos valores guardados.',
        'differs' => ':count página difere dos valores guardados: faltam :added linhas, :removed a mais, :resorted fora de ordem.|:count páginas diferem dos valores guardados: faltam :added linhas, :removed a mais, :resorted fora de ordem.',
        'queued' => 'Reconstrução do índice iniciada.',
    ],
];
