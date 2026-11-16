<?php

declare(strict_types=1);

return [
    'title' => 'Índice de pesquisa',
    'description' => 'As palavras pelas quais a pesquisa encontra páginas: o título e os campos que cada modelo marca como pesquisáveis.',
    'denied' => 'Não pode gerir o índice de pesquisa.',
    'rebuilt' => '{0} Não há páginas para indexar.|{1} 1 página indexada.|[2,*] :count páginas indexadas.',
    'tabs' => [
        'overview' => 'Visão geral',
        'tools' => 'Ferramentas',
    ],
    'rebuild' => [
        'title' => 'Reconstruir',
        'hint' => 'Guardar uma página mantém as suas palavras atualizadas. Reconstrua depois de mudar os campos pesquisáveis ou as definições de um campo, como o rótulo de uma opção. Em sites grandes, reconstrua um modelo de cada vez.',
        'template' => 'Modelos',
        'system_template' => 'Modelos do sistema',
        'no_system_template' => 'Nenhum',
        'every_page' => 'Todas as páginas',
        'button' => 'Reconstruir o índice',
    ],
    'languages' => [
        'title' => 'Idiomas',
        'language' => 'Idioma',
        'indexed' => 'No índice',
        'count' => ':rows de :pages páginas',
        'own' => 'Texto próprio',
        'own_shared' => 'Títulos partilhados',
        'fallback_hint' => 'Cada página tem uma linha em cada idioma. Texto próprio conta as páginas com título próprio nesse idioma; as outras são indexadas com o texto de recurso, para que os visitantes também as encontrem nesse idioma.',
        'incomplete' => 'Precisa de reconstrução',
        'plurals' => 'Singular e plural',
        'plurals_on' => 'Encontra ambos',
        'plurals_off' => 'Só as palavras exatas',
    ],
    'templates' => [
        'title' => 'O que cada modelo indexa',
        'hint' => 'Campos marcados como pesquisáveis cujo tipo tem texto, como texto, área de texto e opções. As páginas sem URL também são indexadas; a pesquisa do site só as mostra se as pedir.',
        'template' => 'Modelo',
        'pages' => 'Páginas',
        'fields' => 'Campos indexados',
        'system' => 'Modelos do sistema',
        'system_hint' => 'Modelos que o Lynx e os seus módulos guardam para si, como o lixo ou as páginas de definições.',
        'owner' => 'Pertence a',
        'no_url' => 'Sem URL',
    ],
    'lookup' => [
        'title' => 'Testar Palavras Indexadas',
        'hint' => 'Procure uma página para ver as palavras pelas quais a pesquisa a encontra, em cada idioma.',
        'page' => 'Caminho ou id da página',
        'placeholder' => 'Por exemplo /sobre ou 42',
        'missing' => 'Não há nenhuma página com esse caminho ou id.',
        'not_indexed' => 'Esta página ainda não tem palavras no índice. Reconstrua o índice.',
    ],
];
