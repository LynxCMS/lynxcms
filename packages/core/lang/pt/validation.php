<?php

declare(strict_types=1);

return [
    'or' => ' ou ',
    'page' => [
        'missing' => 'A página [:page] não existe.',
        'ambiguous' => 'Há várias páginas com o nome [:name]; indique o caminho ou o id da página.',
        'being_edited' => 'A página [:page] está a ser editada por :holder até às :time.',
        'being_edited_retry' => 'A página [:page] está a ser editada por :holder até às :time. Tente novamente depois.',
        'conflict' => 'A página [:page] foi guardada por :holder em :time, depois de a abrir. Recarregue-a para ver essas alterações; as suas não foram guardadas.',
        'locked' => 'A página [:page] está bloqueada. Desbloqueie-a primeiro (pages.status com remove ["locked"]).',
        'switch_template' => 'Use pages.switch_template para mudar o modelo de uma página.',
        'template_required' => 'Uma página nova precisa de um modelo.',
        'system' => [
            'moved' => 'A página [:page] é uma página de sistema de que o CMS ou um módulo depende; não pode ser movida.',
            'switched' => 'A página [:page] é uma página de sistema de que o CMS ou um módulo depende; não pode mudar de modelo.',
            'status' => 'A página [:page] é uma página de sistema de que o CMS ou um módulo depende; não pode ser ocultada, despublicada ou bloqueada.',
        ],
        'delete' => [
            'locked' => 'A página [:page] está bloqueada. Desbloqueie-a primeiro.',
            'locked_below' => 'A página [:page], abaixo de [:above], está bloqueada. Desbloqueie-a primeiro.',
            'system' => 'A página [:page] é uma página de sistema de que o CMS ou um módulo depende; não pode ser eliminada.',
            'system_below' => 'A página [:page], abaixo de [:above], é uma página de sistema de que o CMS ou um módulo depende; não pode ser eliminada.',
        ],
    ],
    'parent' => [
        'required' => 'É preciso indicar a página superior.',
        'missing' => 'A página superior [:page] não existe.',
        'trash' => 'Não é possível criar páginas no lixo.',
        'reference' => 'A página superior indica-se pelo id ou pelo caminho.',
        'no_path' => 'Não há nenhuma página em [:path].',
    ],
    'template' => [
        'missing' => 'O modelo [:template] não existe.',
        'singleton' => 'O modelo [:template] só permite uma página e já a tem.',
    ],
    'language' => [
        'missing' => 'Não existe o idioma [:language].',
    ],
    'names' => [
        'empty' => 'O nome da página não pode ficar vazio.',
        'required' => 'É preciso um nome no idioma predefinido.',
        'required_or_title' => 'É preciso um nome ou um título no idioma predefinido.',
        'taken' => 'Outra página com a mesma página superior já usa o nome [:name].',
        'unique_taken' => 'O nome [:name] pertence à página única :page; escolha outro nome.',
        'unique_used' => 'O nome de uma página única não pode ser usado noutro sítio, mas [:name] já é usado por :page.',
    ],
    'values' => [
        'required' => 'Preencha o campo :field.',
        'required_default' => 'Preencha o campo :field no idioma predefinido.',
        'required_after_saving' => 'O campo :field é obrigatório. Crie a página sem a publicar, preencha-o e depois publique.',
        'no_field' => 'Este modelo não tem o campo [:field].',
        'template_no_field' => 'O modelo [:template] não tem o campo [:field].',
        'not_translatable' => 'O campo [:field] não é traduzível: indique um só valor, em vez de um por idioma.',
        'not_an_option' => '[:values] não é uma das opções: :options.',
        'component' => 'O componente [:component] não está registado ou não é permitido aqui.',
        'url' => 'Escreva um endereço que comece por https://.',
        'url_or_path' => 'Escreva um endereço que comece por https:// ou um caminho que comece por /.',
        'page_template' => 'Escolha páginas que usem :templates: [:page] usa :template.',
        'pages_max' => 'Escolha no máximo :count página.|Escolha no máximo :count páginas.',
        'pages_min' => 'Escolha pelo menos :count página.|Escolha pelo menos :count páginas.',
    ],
    'items' => [
        'list' => 'Os itens têm de ser uma lista.',
        'key' => 'A chave de um item é um ULID: não a indique em itens novos.',
        'max' => 'Pode haver no máximo :count item.|Pode haver no máximo :count itens.',
        'min' => 'Tem de haver pelo menos :count item.|Tem de haver pelo menos :count itens.',
        'type_required' => 'Cada bloco precisa de um tipo: :types.',
        'no_type' => 'Não existe o tipo de bloco [:type]. Use um destes: :types.',
        'type_not_allowed' => 'Os blocos do tipo [:type] não são permitidos aqui. Permitidos: :types.',
    ],
];
