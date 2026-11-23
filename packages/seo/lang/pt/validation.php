<?php

declare(strict_types=1);

return [
    'no_url' => 'O modelo [:template] não tem URL: os motores de busca nunca veem as suas páginas.',
    'field' => 'O campo [:field] já existe com outras definições; o módulo de SEO precisa de [:needs].',
    'mapping' => [
        'type' => '[:type] não é um tipo que o Lynx associe. Use um destes: :types.',
        'template' => 'O modelo [:template] não existe ou não tem URL.',
        'twice' => 'O modelo [:template] já está associado acima.',
        'not_offered' => '[:type] não é um tipo que os dados estruturados ofereçam: use um destes: :types.',
        'line' => 'Linha [:line]: escreva propriedade: origem.',
        'no_property' => ':type não tem a propriedade [:property].',
        'organization' => '[:source] só serve em propriedades que aceitam uma página, como author, provider, organizer ou worksFor.',
        'built_in' => '[:source] não é uma origem integrada aqui: use :sources.',
        'no_field' => 'O modelo não tem o campo [:field].',
        'no_subfield' => 'A lista [:list] não tem o subcampo [:field].',
        'needs' => 'A propriedade [:property] precisa de :kind; [:field] é um campo do tipo :type.',
    ],
    'kinds' => [
        'image' => 'um campo de imagem',
        'date' => 'um campo de data',
        'number' => 'um campo de número',
        'page' => 'um campo de referência a páginas',
        'list' => 'um campo repetidor ou matriz',
        'enum' => 'um campo de escolha ou de texto',
        'lines' => 'um campo de texto',
        'url' => 'um campo de URL, de texto ou de referência a páginas',
        'text' => 'um campo de texto',
    ],
];
