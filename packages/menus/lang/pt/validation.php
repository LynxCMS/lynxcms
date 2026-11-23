<?php

declare(strict_types=1);

return [
    'menu' => [
        'taken' => 'Já existe um menu com o nome [:menu].',
        'title' => 'É preciso um título no idioma predefinido.',
        'missing' => 'Não existe o menu [:menu].',
        'not_found' => 'O menu [:menu] não existe.',
    ],
    'field' => 'O campo [:field] já existe com outras definições; o construtor de menus precisa de [:needs].',
    'item' => [
        'missing' => 'O item de menu [:item] não existe.',
        'other_menu' => 'O item de menu [:item] não está no menu [:menu].',
        'parent' => 'O item superior tem de ser este menu ou um dos seus itens.',
        'label' => 'É preciso um rótulo no idioma predefinido.',
        'no_language' => 'Mostre o item em pelo menos um idioma.',
        'page' => 'Escolha uma página que exista e tenha URL.',
        'url' => 'É preciso um URL no idioma predefinido.',
        'url_scheme' => 'O URL em :language tem de ser relativo, uma #âncora, ou usar :schemes.',
        'target' => 'Use _blank, _self, _parent, _top ou o nome de uma moldura (letras, algarismos, - e _).',
    ],
    'languages' => 'Idioma desconhecido: [:languages].',
];
