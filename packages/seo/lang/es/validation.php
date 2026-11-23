<?php

declare(strict_types=1);

return [
    'no_url' => 'La plantilla [:template] no tiene URL: los buscadores nunca ven sus páginas.',
    'field' => 'El campo [:field] ya existe con otros ajustes; el módulo de SEO necesita [:needs].',
    'mapping' => [
        'type' => '[:type] no es un tipo que Lynx asocie. Usa uno de estos: :types.',
        'template' => 'La plantilla [:template] no existe o no tiene URL.',
        'twice' => 'La plantilla [:template] ya está asociada arriba.',
        'not_offered' => '[:type] no es un tipo que ofrezcan los datos estructurados: usa uno de estos: :types.',
        'line' => 'Línea [:line]: escribe propiedad: origen.',
        'no_property' => ':type no tiene la propiedad [:property].',
        'organization' => '[:source] solo sirve en propiedades que aceptan una página, como author, provider, organizer o worksFor.',
        'built_in' => '[:source] no es un origen integrado aquí: usa :sources.',
        'no_field' => 'La plantilla no tiene el campo [:field].',
        'no_subfield' => 'La lista [:list] no tiene el subcampo [:field].',
        'needs' => 'La propiedad [:property] necesita :kind; [:field] es un campo de tipo :type.',
    ],
    'kinds' => [
        'image' => 'un campo de imagen',
        'date' => 'un campo de fecha',
        'number' => 'un campo numérico',
        'page' => 'un campo de referencia a páginas',
        'list' => 'un campo repetidor o matriz',
        'enum' => 'un campo de elección o de texto',
        'lines' => 'un campo de texto',
        'url' => 'un campo de URL, de texto o de referencia a páginas',
        'text' => 'un campo de texto',
    ],
];
