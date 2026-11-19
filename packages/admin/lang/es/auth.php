<?php

declare(strict_types=1);

return [
    'email' => 'Correo electrónico',
    'password' => 'Contraseña',
    'repeat_password' => 'Repite la contraseña',
    'login' => [
        'title' => 'Iniciar sesión',
        'description' => 'Inicia sesión para gestionar el contenido de este sitio.',
        'passkey' => 'Iniciar sesión con una llave de acceso',
        'or_email' => 'o con tu correo electrónico',
        'forgot' => '¿Has olvidado tu contraseña?',
        'remember' => 'Mantener la sesión iniciada',
        'submit' => 'Iniciar sesión',
    ],
    'confirm' => [
        'title' => 'Confirma tu contraseña',
        'description' => 'Esta es una zona protegida. Escribe tu contraseña para continuar.',
        'submit' => 'Confirmar',
    ],
    'forgot' => [
        'title' => '¿Has olvidado tu contraseña?',
        'description' => 'Te enviaremos por correo un enlace para elegir una nueva.',
        'submit' => 'Enviar un enlace para restablecerla',
        'back' => 'Volver al inicio de sesión',
    ],
    'temporary' => [
        'expired' => 'Esta contraseña temporal ha caducado. Pide una nueva a quien te la dio.',
    ],
    'new_password' => [
        'title' => 'Elige tu contraseña',
        'description' => 'Has iniciado sesión con una contraseña temporal. Elige la tuya para continuar.',
        'password' => 'Nueva contraseña',
        'submit' => 'Guardar contraseña',
        'same' => 'Elige una contraseña distinta de la temporal.',
    ],
    'invitation' => [
        'welcome' => 'Te damos la bienvenida',
        'welcome_name' => 'Te damos la bienvenida, :name',
        'description' => 'Elige una contraseña para terminar de configurar tu cuenta.',
        'password' => 'Elige una contraseña',
        'submit' => 'Guardar la contraseña e iniciar sesión',
        'expired' => 'Esta invitación ha caducado o ya se ha usado. Pide una nueva.',
    ],
    'reset' => [
        'title' => 'Elige una contraseña nueva',
        'password' => 'Contraseña nueva',
        'submit' => 'Guardar la contraseña',
    ],
    'two_factor' => [
        'title' => 'Verificación en dos pasos',
        'description' => 'Escribe el código de tu aplicación de autenticación o uno de tus códigos de recuperación.',
        'code' => 'Código de autenticación',
        'recovery_code' => 'Código de recuperación',
        'submit' => 'Continuar',
        'use_code' => 'Usar un código de autenticación',
        'use_recovery_code' => 'Usar un código de recuperación',
    ],
];
