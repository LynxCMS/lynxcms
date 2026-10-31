<?php

declare(strict_types=1);

return [
    'menu' => [
        'appearance' => 'Aparência',
        'light' => 'Claro',
        'dark' => 'Escuro',
        'system' => 'Sistema',
        'language' => 'Idioma',
        'translation_hints' => 'Dicas de tradução',
        'security' => 'Segurança',
    ],
    'security' => [
        'title' => 'Segurança da conta',
        'description' => 'A sua palavra-passe, autenticação de dois fatores e chaves de acesso.',
    ],
    'password' => [
        'heading' => 'Palavra-passe',
        'current' => 'Palavra-passe atual',
        'new' => 'Nova palavra-passe',
        'repeat' => 'Repita a nova palavra-passe',
        'submit' => 'Alterar palavra-passe',
        'changed' => 'Palavra-passe alterada.',
    ],
    'two_factor' => [
        'heading' => 'Autenticação de dois fatores',
        'description' => 'Depois da palavra-passe, o início de sessão pede um código de uma aplicação de autenticação no seu telemóvel.',
        'scan' => 'Leia o código QR com a sua aplicação de autenticação ou introduza a chave de configuração. Depois, escreva o código que ela mostrar.',
        'setup_key' => 'Chave de configuração:',
        'code' => 'Código',
        'confirm' => 'Confirmar',
        'cancel' => 'Cancelar',
        'invalid_code' => 'Esse código não é válido. Experimente o mais recente da sua aplicação.',
        'on' => 'Ativa',
        'on_description' => 'O início de sessão pede um código.',
        'recovery_heading' => 'Códigos de recuperação',
        'recovery_description' => 'Cada código permite iniciar sessão uma vez se perder o telemóvel. Guarde-os num gestor de palavras-passe; só são mostrados agora.',
        'new_recovery_codes' => 'Novos códigos de recuperação',
        'turn_off' => 'Desativar',
        'turn_on' => 'Ativar',
    ],
    'passkeys' => [
        'heading' => 'Chaves de acesso',
        'description' => 'Inicie sessão com a impressão digital, o rosto ou o PIN do dispositivo em vez de uma palavra-passe.',
        'added_on' => 'Adicionada :when',
        'last_used' => 'última utilização :when',
        'remove' => 'Remover',
        'confirm_remove' => 'Remover a chave de acesso :name?',
        'empty' => 'Ainda não há chaves de acesso.',
        'unsupported' => 'Este navegador não suporta chaves de acesso.',
        'name' => 'Nome da chave de acesso',
        'add' => 'Adicionar chave de acesso',
        'added' => 'Chave de acesso adicionada.',
    ],
];
