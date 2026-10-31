<?php

declare(strict_types=1);

return [
    'title' => 'Utilizadores',
    'description' => 'Pessoas que podem iniciar sessão e as funções que têm.',
    'saved' => 'Guardado.',
    'list' => [
        'search' => 'Pesquisar',
        'search_placeholder' => 'Nome ou email',
        'user' => 'Utilizador',
        'roles' => 'Funções',
        'save' => 'Guardar',
        'sent' => 'Enviado :when',
        'pending' => 'Convite pendente',
        'resend' => 'Reenviar convite',
        'empty' => 'Nenhum utilizador corresponde.',
    ],
    'invite' => [
        'heading' => 'Convidar utilizador',
        'help' => 'Recebem um email com uma ligação para escolher a palavra-passe. Não há formulário de registo.',
        'name' => 'Nome',
        'email' => 'Email',
        'roles' => 'Funções',
        'send' => 'Enviar convite',
        'sent' => 'Convite enviado para :email.',
        'sent_again' => 'Convite reenviado para :email.',
        'email_taken' => 'Já existe alguém com este email que pode iniciar sessão.',
        'not_pending' => 'Este utilizador não tem nenhum convite pendente.',
    ],
    'mail' => [
        'subject' => 'Foi convidado para :site',
        'greeting' => 'Olá, :name,',
        'intro' => 'Foi convidado para gerir o conteúdo de :site. Escolha uma palavra-passe para iniciar sessão.',
        'action' => 'Escolher a palavra-passe',
        'outro' => 'A ligação funciona durante :days dias. Se não estava à espera deste convite, ignore este email.',
    ],
];
