<?php

declare(strict_types=1);

return [
    'form' => [
        'name' => 'Use letras minúsculas, algarismos e hífenes, a começar por uma letra, como contacto (no máximo :max caracteres).',
        'taken' => 'Já existe um formulário com o nome [:form].',
        'no_root' => 'Falta a página dos formulários.',
        'failed' => 'Não foi possível criar o formulário.',
        'missing' => 'Não existe o formulário [:form].',
    ],
    'field' => 'O campo [:field] já existe com outras definições; o construtor de formulários precisa de [:needs].',
    'submission' => 'Não existe a resposta [:submission].',
    'import' => [
        'value' => 'Os campos do tipo [:type] não têm a definição [:value].',
    ],
    'builder' => [
        'indented' => 'Os blocos recuados pertencem a um grupo: adicione um grupo acima, ou retire o recuo.',
        'nested' => 'Um grupo não pode conter outro grupo nem um passo.',
        'no_fields' => 'Adicione pelo menos um campo antes de publicar o formulário.',
        'code_form' => 'Um formulário definido em código já usa o nome [:form].',
        'name' => '[:name] não pode ser um nome de campo: use letras minúsculas, algarismos e sublinhados, começando por uma letra.',
        'name_taken' => 'Outro campo já tem o nome [:name]; dê a este um nome próprio.',
        'no_options' => 'Adicione pelo menos uma opção.',
        'show_if' => 'Não há nenhum campo [:field] antes deste.',
        'number' => 'Compare com um número, por exemplo ">= 18".',
    ],
    'turnstile_secret' => 'Indique também a chave secreta.',
];
