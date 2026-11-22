<?php

declare(strict_types=1);

return [
    'field' => [
        'missing' => 'O campo [:field] não existe.',
        'create_first' => 'O campo [:field] não existe. Crie-o primeiro com lynx()->fields->add(\':field\', ...) ou com a ação fields.create.',
        'problem' => 'Campo [:field]: :problem',
        'not_html' => 'O campo [:field] não é um campo html.',
        'reserved' => 'O nome ":name" está reservado, porque as páginas e os seletores já o usam. Escolha outro nome.',
        'name_max' => 'O nome do campo não pode ter mais de :max caracteres.',
        'table' => 'Já existe uma tabela na base de dados com o nome :table.',
        'unknown_type' => 'Tipo de campo desconhecido: [:type]. Disponíveis: :types.',
        'not_translatable' => 'Os campos do tipo [:type] não podem ser traduzíveis.',
        'translatable' => 'O campo [:field] já é traduzível.',
        'subfield_of' => 'O campo [:field] é um subcampo de :fields. Remova-o primeiro daí.',
        'nested' => 'O campo [:field] já guarda itens; os itens não podem conter outros. Use a profundidade (depth).',
        'per_use' => 'O campo [:field] não permite definir [:settings] onde é usado: defina no próprio campo.',
    ],
    'items' => [
        'no_fields' => 'Indique pelo menos um campo.',
        'no_types' => 'Adicione pelo menos um tipo de bloco.',
    ],
    'choice' => [
        'default' => 'A predefinição é o valor de uma opção, ou uma lista de valores.',
        'single' => 'Uma escolha única tem uma só predefinição.',
        'not_an_option' => 'A predefinição [:default] não é uma das opções.',
        'value' => '[:value] não é um valor de opção válido: use letras minúsculas, algarismos, hífenes e sublinhados.',
        'twice' => 'O valor de opção [:value] aparece duas vezes.',
    ],
    'formatter' => [
        'unknown' => 'Formatador desconhecido: [:formatter]. Disponíveis: :formatters.',
        'type' => 'O formatador [:formatter] não se aplica a campos do tipo :type.',
    ],
    'language' => [
        'delete_default' => 'O idioma predefinido não pode ser eliminado.',
        'code_taken' => 'O código [:code] já é usado por outro idioma.',
        'prefix_taken' => 'O prefixo [:prefix] já é usado por outro idioma.',
        'default_fallback' => 'O idioma predefinido é o fim de todas as cadeias de idiomas alternativos e não pode recorrer a outro idioma.',
        'self_fallback' => 'Um idioma não pode recorrer a si próprio.',
        'cycle' => 'Recorrer a :language criaria um ciclo.',
    ],
    'text' => [
        'max' => 'O campo :attribute não pode ter mais de :max caracteres.',
        'type' => 'O campo :attribute tem de ser texto, ou um texto por código de idioma.',
        'language' => 'O campo :attribute usa o idioma [:language], que não existe.',
        'language_max' => 'O campo :attribute em [:language] tem de ser um texto com no máximo :max caracteres.',
    ],
    'editor' => [
        'display' => 'editor é um destes valores: :values.',
        'width' => 'width é um destes valores: :values.',
        'icon' => 'icon é o nome de um ícone Lucide, como "calendar".',
        'required' => 'um campo :display não pode ser obrigatório: os editores não o conseguiriam preencher.',
    ],
    'section' => [
        'missing' => 'O modelo [:template] não tem a secção [:section].',
        'taken' => 'O modelo [:template] já tem uma secção [:section].',
        'clashes' => 'O modelo [:template] já tem [:fields]. Mude o nome dos campos em conflito, ou remova-os primeiro desse modelo.',
        'prefix' => 'O campo [:field] já existe: escolha outro prefixo.',
        'key' => 'A chave de secção [:section] só pode ter letras minúsculas, algarismos, - e _, e começa por uma letra.',
        'twice' => 'A secção [:section] aparece duas vezes.',
        'nested' => 'Uma secção não pode estar dentro de outra: [:nested] está dentro de [:section].',
        'place' => 'O campo [:field] está na secção [:section]: defina o separador e a coluna na secção.',
    ],
    'layout' => [
        'field' => 'Campo [:name]',
        'section' => 'Secção [:name]',
        'tab' => 'Separador [:name]',
        'problem' => ':what: :problem',
        'no_section' => 'O campo [:field] indica a secção [:section], que este modelo não tem.',
        'no_tab' => ':what: este modelo não tem o separador [:tab].',
        'tab_key' => 'A chave de separador [:tab] tem de ser única, só com letras minúsculas, algarismos, - e _, e diferente de: :reserved.',
        'column' => ':what: a coluna é main ou sidebar; [:column] não.',
    ],
    'condition' => [
        'problem' => 'O :setting de [:field]: :problem',
        'itself' => 'O :setting de [:field] não pode depender do próprio campo.',
        'missing' => 'O :setting de [:field] lê [:fields], que não está neste modelo.',
        'unreadable' => 'Não foi possível ler a condição [:condition]: escreva campo=valor, por exemplo format=online|hybrid.',
        'no_value' => 'Não foi possível ler a condição [:condition]: [:operator] precisa de um valor.',
    ],
    'usage' => [
        'trashed' => 'no lixo',
        'more' => ', e mais :count',
        'refusal' => 'O modelo [:template] é usado por :count página: :pages. Mude-a para outro modelo (pages.switch_template) ou elimine-a, e depois elimine o modelo.|O modelo [:template] é usado por :count páginas: :pages. Mude-as para outro modelo (pages.switch_template) ou elimine-as, e depois elimine o modelo.',
    ],
];
