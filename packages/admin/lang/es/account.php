<?php

declare(strict_types=1);

return [
    'menu' => [
        'appearance' => 'Apariencia',
        'light' => 'Claro',
        'dark' => 'Oscuro',
        'system' => 'Sistema',
        'language' => 'Idioma',
        'translation_hints' => 'Pistas de traducción',
        'shortcuts' => 'Atajos de teclado',
        'security' => 'Seguridad',
    ],
    'security' => [
        'title' => 'Seguridad de la cuenta',
        'description' => 'Tu contraseña, la verificación en dos pasos y las llaves de acceso.',
    ],
    'password' => [
        'heading' => 'Contraseña',
        'current' => 'Contraseña actual',
        'new' => 'Contraseña nueva',
        'repeat' => 'Repite la contraseña nueva',
        'submit' => 'Cambiar la contraseña',
        'changed' => 'Contraseña cambiada.',
    ],
    'two_factor' => [
        'heading' => 'Verificación en dos pasos',
        'description' => 'Después de la contraseña, al iniciar sesión se te pide un código de una aplicación de autenticación en tu móvil.',
        'scan' => 'Escanea el código QR con tu aplicación de autenticación o escribe la clave de configuración. Después escribe el código que muestra.',
        'setup_key' => 'Clave de configuración:',
        'code' => 'Código',
        'confirm' => 'Confirmar',
        'cancel' => 'Cancelar',
        'invalid_code' => 'Ese código no es válido. Prueba con el más reciente de tu aplicación.',
        'on' => 'Activada',
        'on_description' => 'Al iniciar sesión se pide un código.',
        'recovery_heading' => 'Códigos de recuperación',
        'recovery_description' => 'Cada código te permite iniciar sesión una vez si pierdes el móvil. Guárdalos en un gestor de contraseñas; solo se muestran ahora.',
        'new_recovery_codes' => 'Nuevos códigos de recuperación',
        'turn_off' => 'Desactivar',
        'turn_on' => 'Activar',
    ],
    'passkeys' => [
        'heading' => 'Llaves de acceso',
        'description' => 'Inicia sesión con tu huella, tu cara o el PIN del dispositivo en lugar de una contraseña.',
        'added_on' => 'Añadida :when',
        'last_used' => 'usada por última vez :when',
        'remove' => 'Quitar',
        'confirm_remove' => '¿Quitar la llave de acceso :name?',
        'empty' => 'Aún no hay llaves de acceso.',
        'unsupported' => 'Este navegador no admite llaves de acceso.',
        'name' => 'Nombre de la llave de acceso',
        'add' => 'Añadir llave de acceso',
        'added' => 'Llave de acceso añadida.',
    ],
];
