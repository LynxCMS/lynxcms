<?php

declare(strict_types=1);

return [
    'field' => [
        'missing' => 'El campo [:field] no existe.',
        'create_first' => 'El campo [:field] no existe. Créalo primero con lynx()->fields->add(\':field\', ...) o con la acción fields.create.',
        'problem' => 'Campo [:field]: :problem',
        'not_html' => 'El campo [:field] no es un campo html.',
        'reserved' => 'El nombre ":name" está reservado, porque las páginas y los selectores ya lo usan. Elige otro nombre.',
        'name_max' => 'El nombre del campo no puede tener más de :max caracteres.',
        'table' => 'Ya existe una tabla en la base de datos con el nombre :table.',
        'unknown_type' => 'Tipo de campo desconocido: [:type]. Disponibles: :types.',
        'not_translatable' => 'Los campos de tipo [:type] no pueden ser traducibles.',
        'translatable' => 'El campo [:field] ya es traducible.',
        'subfield_of' => 'El campo [:field] es un subcampo de :fields. Quítalo primero de ahí.',
        'nested' => 'El campo [:field] ya guarda elementos; los elementos no pueden contener otros. Usa la profundidad (depth).',
        'per_use' => 'El campo [:field] no permite definir [:settings] donde se usa: defínelo en el propio campo.',
    ],
    'items' => [
        'no_fields' => 'Indica al menos un campo.',
        'no_types' => 'Añade al menos un tipo de bloque.',
    ],
    'choice' => [
        'default' => 'El valor predeterminado es el valor de una opción, o una lista de valores.',
        'single' => 'Una elección única tiene un solo valor predeterminado.',
        'not_an_option' => 'El valor predeterminado [:default] no es una de las opciones.',
        'value' => '[:value] no es un valor de opción válido: usa letras minúsculas, dígitos, guiones y guiones bajos.',
        'twice' => 'El valor de opción [:value] aparece dos veces.',
    ],
    'formatter' => [
        'unknown' => 'Formateador desconocido: [:formatter]. Disponibles: :formatters.',
        'type' => 'El formateador [:formatter] no se aplica a campos de tipo :type.',
    ],
    'language' => [
        'delete_default' => 'El idioma predeterminado no se puede eliminar.',
        'code_taken' => 'El código [:code] ya lo usa otro idioma.',
        'prefix_taken' => 'El prefijo [:prefix] ya lo usa otro idioma.',
        'default_fallback' => 'El idioma predeterminado es el final de todas las cadenas de respaldo y no puede recurrir a otro idioma.',
        'self_fallback' => 'Un idioma no puede recurrir a sí mismo.',
        'cycle' => 'Recurrir a :language crearía un ciclo.',
    ],
    'text' => [
        'max' => 'El campo :attribute no puede tener más de :max caracteres.',
        'type' => 'El campo :attribute debe ser texto, o un texto por código de idioma.',
        'language' => 'El campo :attribute usa el idioma [:language], que no existe.',
        'language_max' => 'El campo :attribute en [:language] debe ser un texto de como máximo :max caracteres.',
    ],
    'editor' => [
        'display' => 'editor es uno de estos valores: :values.',
        'width' => 'width es uno de estos valores: :values.',
        'icon' => 'icon es el nombre de un icono de Lucide, como "calendar".',
        'required' => 'un campo :display no puede ser obligatorio: los editores no podrían rellenarlo.',
    ],
    'section' => [
        'missing' => 'La plantilla [:template] no tiene la sección [:section].',
        'taken' => 'La plantilla [:template] ya tiene una sección [:section].',
        'clashes' => 'La plantilla [:template] ya tiene [:fields]. Cambia el nombre de los campos en conflicto, o quítalos primero de esa plantilla.',
        'prefix' => 'El campo [:field] ya existe: elige otro prefijo.',
        'key' => 'La clave de sección [:section] solo puede tener letras minúsculas, dígitos, - y _, y empieza por una letra.',
        'twice' => 'La sección [:section] aparece dos veces.',
        'nested' => 'Una sección no puede estar dentro de otra: [:nested] está dentro de [:section].',
        'place' => 'El campo [:field] está en la sección [:section]: define la pestaña y la columna en la sección.',
    ],
    'layout' => [
        'field' => 'Campo [:name]',
        'section' => 'Sección [:name]',
        'tab' => 'Pestaña [:name]',
        'problem' => ':what: :problem',
        'no_section' => 'El campo [:field] indica la sección [:section], que esta plantilla no tiene.',
        'no_tab' => ':what: esta plantilla no tiene la pestaña [:tab].',
        'tab_key' => 'La clave de pestaña [:tab] debe ser única, solo con letras minúsculas, dígitos, - y _, y distinta de: :reserved.',
        'column' => ':what: la columna es main o sidebar; [:column] no.',
    ],
    'condition' => [
        'problem' => 'El :setting de [:field]: :problem',
        'itself' => 'El :setting de [:field] no puede depender del propio campo.',
        'missing' => 'El :setting de [:field] lee [:fields], que no está en esta plantilla.',
        'unreadable' => 'No se pudo leer la condición [:condition]: escribe campo=valor, por ejemplo format=online|hybrid.',
        'no_value' => 'No se pudo leer la condición [:condition]: [:operator] necesita un valor.',
    ],
    'usage' => [
        'trashed' => 'en la papelera',
        'more' => ', y :count más',
        'refusal' => 'La plantilla [:template] la usa :count página: :pages. Cámbiala a otra plantilla (pages.switch_template) o elimínala, y después elimina la plantilla.|La plantilla [:template] la usan :count páginas: :pages. Cámbialas a otra plantilla (pages.switch_template) o elimínalas, y después elimina la plantilla.',
    ],
];
