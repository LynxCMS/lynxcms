<?php

declare(strict_types=1);

return [
    'or' => ' o ',
    'page' => [
        'missing' => 'La página [:page] no existe.',
        'ambiguous' => 'Hay varias páginas con el nombre [:name]; indica la ruta o el id de la página.',
        'being_edited' => 'La página [:page] está siendo editada por :holder hasta las :time.',
        'being_edited_retry' => 'La página [:page] está siendo editada por :holder hasta las :time. Inténtalo de nuevo después.',
        'conflict' => 'La página [:page] fue guardada por :holder el :time, después de que la abrieras. Recárgala para ver esos cambios; los tuyos no se guardaron.',
        'locked' => 'La página [:page] está bloqueada. Desbloquéala primero (pages.status con remove ["locked"]).',
        'switch_template' => 'Usa pages.switch_template para cambiar la plantilla de una página.',
        'template_required' => 'Una página nueva necesita una plantilla.',
        'system' => [
            'moved' => 'La página [:page] es una página de sistema de la que depende el CMS o un módulo; no se puede mover.',
            'switched' => 'La página [:page] es una página de sistema de la que depende el CMS o un módulo; no se puede cambiar de plantilla.',
            'status' => 'La página [:page] es una página de sistema de la que depende el CMS o un módulo; no se puede ocultar, despublicar ni bloquear.',
        ],
        'delete' => [
            'locked' => 'La página [:page] está bloqueada. Desbloquéala primero.',
            'locked_below' => 'La página [:page], debajo de [:above], está bloqueada. Desbloquéala primero.',
            'system' => 'La página [:page] es una página de sistema de la que depende el CMS o un módulo; no se puede eliminar.',
            'system_below' => 'La página [:page], debajo de [:above], es una página de sistema de la que depende el CMS o un módulo; no se puede eliminar.',
        ],
    ],
    'parent' => [
        'required' => 'Hay que indicar la página superior.',
        'missing' => 'La página superior [:page] no existe.',
        'trash' => 'No se pueden crear páginas en la papelera.',
        'reference' => 'La página superior se indica con su id o su ruta.',
        'no_path' => 'No hay ninguna página en [:path].',
    ],
    'template' => [
        'missing' => 'La plantilla [:template] no existe.',
        'singleton' => 'La plantilla [:template] solo permite una página y ya la tiene.',
    ],
    'language' => [
        'missing' => 'No existe el idioma [:language].',
    ],
    'names' => [
        'empty' => 'El nombre de la página no puede quedar vacío.',
        'required' => 'Se necesita un nombre en el idioma predeterminado.',
        'required_or_title' => 'Se necesita un nombre o un título en el idioma predeterminado.',
        'taken' => 'Otra página con la misma página superior ya usa el nombre [:name].',
        'unique_taken' => 'El nombre [:name] pertenece a la página única :page; elige otro nombre.',
        'unique_used' => 'El nombre de una página única no puede usarse en otro sitio, pero [:name] ya lo usa :page.',
    ],
    'values' => [
        'required' => 'Rellena el campo :field.',
        'required_default' => 'Rellena el campo :field en el idioma predeterminado.',
        'required_after_saving' => 'El campo :field es obligatorio. Crea la página sin publicarla, rellénalo y después publica.',
        'no_field' => 'Esta plantilla no tiene el campo [:field].',
        'template_no_field' => 'La plantilla [:template] no tiene el campo [:field].',
        'not_translatable' => 'El campo [:field] no es traducible: indica un solo valor, en lugar de uno por idioma.',
        'not_an_option' => '[:values] no es una de las opciones: :options.',
        'component' => 'El componente [:component] no está registrado o no se permite aquí.',
        'url' => 'Escribe una dirección que empiece por https://.',
        'url_or_path' => 'Escribe una dirección que empiece por https:// o una ruta que empiece por /.',
        'page_template' => 'Elige páginas que usen :templates: [:page] usa :template.',
        'pages_max' => 'Elige como máximo :count página.|Elige como máximo :count páginas.',
        'pages_min' => 'Elige al menos :count página.|Elige al menos :count páginas.',
    ],
    'items' => [
        'list' => 'Los elementos deben ser una lista.',
        'key' => 'La clave de un elemento es un ULID: no la indiques en elementos nuevos.',
        'max' => 'Puede haber como máximo :count elemento.|Puede haber como máximo :count elementos.',
        'min' => 'Debe haber al menos :count elemento.|Debe haber al menos :count elementos.',
        'type_required' => 'Cada bloque necesita un tipo: :types.',
        'no_type' => 'No existe el tipo de bloque [:type]. Usa uno de estos: :types.',
        'type_not_allowed' => 'Los bloques del tipo [:type] no se permiten aquí. Permitidos: :types.',
    ],
];
