<?php

declare(strict_types=1);

return [
    'form' => [
        'taken' => 'Ya existe un formulario con el nombre [:form].',
        'no_root' => 'Falta la página de los formularios.',
        'failed' => 'No se pudo crear el formulario.',
        'missing' => 'No existe el formulario [:form].',
    ],
    'field' => 'El campo [:field] ya existe con otros ajustes; el creador de formularios necesita [:needs].',
    'submission' => 'No existe el envío [:submission].',
    'builder' => [
        'indented' => 'Los bloques con sangría pertenecen a un grupo: añade un grupo encima, o quita la sangría.',
        'nested' => 'Un grupo no puede contener otro grupo ni un paso.',
        'no_fields' => 'Añade al menos un campo antes de publicar el formulario.',
        'code_form' => 'Un formulario definido en código ya usa el nombre [:form].',
        'name' => '[:name] no puede ser un nombre de campo: usa letras minúsculas, dígitos y guiones bajos, empezando por una letra.',
        'name_taken' => 'Otro campo ya tiene el nombre [:name]; dale a este un nombre propio.',
        'no_options' => 'Añade al menos una opción.',
        'show_if' => 'No hay ningún campo [:field] antes de este.',
        'number' => 'Compara con un número, por ejemplo ">= 18".',
    ],
];
