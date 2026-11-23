<?php

declare(strict_types=1);

return [
    'title' => 'Funções',
    'description' => 'O que cada função pode fazer, em que modelos e ramos.',
    'new' => 'Nova função',
    'heading' => 'Função :name',
    'denied' => 'Só os superutilizadores gerem funções.',
    'saved' => 'Guardado.',
    'form' => [
        'heading' => 'Função',
        'name' => 'Nome',
        'name_help' => 'Letras minúsculas, algarismos, hífenes e sublinhados, por exemplo course-editor.',
        'label' => 'Rótulo',
        'label_placeholder' => 'A partir do nome',
        'superuser' => 'Superutilizador: pode fazer tudo, incluindo gerir funções',
        'permissions' => 'Permissões',
        'permissions_help' => 'As permissões de páginas podem ser limitadas a modelos e a ramos da árvore de páginas (uma página e tudo o que está abaixo dela). Sem escolha significa em todo o lado.',
        'only_templates' => 'Apenas estes modelos',
        'only_under' => 'Apenas dentro destas páginas',
        'search' => 'Pesquise uma página',
        'create' => 'Criar função',
        'save' => 'Guardar',
        'back' => 'Voltar às funções',
        'delete' => 'Eliminar função',
        'confirm' => 'Eliminar :name? 1 utilizador perde-a.|Eliminar :name? :count utilizadores perdem-na.',
        'delete_named' => 'Eliminar a função :name',
        'cancel' => 'Cancelar',
    ],
    'list' => [
        'name' => 'Nome',
        'label' => 'Rótulo',
        'grants' => 'Permissões',
        'users' => 'Utilizadores',
        'superuser' => 'superutilizador',
        'all' => 'Todas',
        'empty' => 'Ainda não há funções.',
        'empty_help' => 'Enquanto nenhum utilizador tiver uma função, qualquer utilizador com sessão iniciada pode fazer tudo. Uma função diz o que os seus utilizadores podem fazer, e onde.',
    ],
];
