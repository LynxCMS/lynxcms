<?php

declare(strict_types=1);

return [
    'title' => 'Usuarios',
    'description' => 'Las personas que pueden iniciar sesión y los roles que tienen.',
    'saved' => 'Guardado.',
    'list' => [
        'search' => 'Buscar',
        'search_placeholder' => 'Nombre o correo electrónico',
        'user' => 'Usuario',
        'roles' => 'Roles',
        'save' => 'Guardar',
        'sent' => 'Enviada :when',
        'pending' => 'Invitación pendiente',
        'resend' => 'Reenviar invitación',
        'empty' => 'Ningún usuario coincide.',
        'empty_help' => 'Prueba con una búsqueda más corta, o con parte de un nombre o de una dirección de correo.',
    ],
    'invite' => [
        'heading' => 'Invitar a un usuario',
        'help' => 'Recibirá un correo con un enlace para elegir su contraseña. No hay formulario de registro.',
        'name' => 'Nombre',
        'email' => 'Correo electrónico',
        'roles' => 'Roles',
        'send' => 'Enviar invitación',
        'sent' => 'Invitación enviada a :email.',
        'sent_again' => 'Invitación reenviada a :email.',
        'email_taken' => 'Alguien con este correo ya puede iniciar sesión.',
        'not_pending' => 'Este usuario no tiene ninguna invitación pendiente.',
    ],
    'mail' => [
        'subject' => 'Te han invitado a :site',
        'greeting' => 'Hola, :name:',
        'intro' => 'Te han invitado a gestionar el contenido de :site. Elige una contraseña para iniciar sesión.',
        'action' => 'Elegir tu contraseña',
        'outro' => 'El enlace funciona durante :days días. Si no esperabas esta invitación, ignora este correo.',
    ],
];
