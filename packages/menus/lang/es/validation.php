<?php

declare(strict_types=1);

return [
    'menu' => [
        'taken' => 'Ya existe un menú con el nombre [:menu].',
        'title' => 'Se necesita un título en el idioma predeterminado.',
        'missing' => 'No existe el menú [:menu].',
        'not_found' => 'El menú [:menu] no existe.',
    ],
    'field' => 'El campo [:field] ya existe con otros ajustes; el creador de menús necesita [:needs].',
    'item' => [
        'missing' => 'El elemento de menú [:item] no existe.',
        'other_menu' => 'El elemento de menú [:item] no está en el menú [:menu].',
        'parent' => 'El elemento superior debe ser este menú o uno de sus elementos.',
        'label' => 'Se necesita una etiqueta en el idioma predeterminado.',
        'no_language' => 'Muestra el elemento en al menos un idioma.',
        'page' => 'Elige una página que exista y tenga URL.',
        'url' => 'Se necesita una URL en el idioma predeterminado.',
        'url_scheme' => 'La URL en :language debe ser relativa, un #fragmento, o usar :schemes.',
        'target' => 'Usa _blank, _self, _parent, _top o el nombre de un marco (letras, dígitos, - y _).',
    ],
    'languages' => 'Idioma desconocido: [:languages].',
];
