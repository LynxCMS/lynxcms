<?php

declare(strict_types=1);

// The text the schema installs, in English => what editors read (ADR-0144).
return [
    'Allowed file types (comma separated)' => 'Tipos de ficheiro permitidos (separados por vírgulas)',
    'Choose one' => 'Escolher um',
    'Choose several' => 'Escolher vários',
    'Condition' => 'Condição',
    'Conditions in the forms docs' => 'Condições na documentação dos formulários',
    'Confirmation mailed to the visitor' => 'Confirmação enviada por email ao visitante',
    'Date' => 'Data',
    'Dropdown' => 'Lista pendente',
    'Email' => 'Email',
    "Examples:\n"
            ."job_title has the answer other: shown when a visitor chooses Other.\n"
            ."country has the answer pt, es: shown for either answer.\n"
            ."age has the answer >= 18: shown for 18 or more. Also >, <, <= and != (not this answer).\n"
            .'newsletter has the answer yes: shown when the tick box is ticked.' => "Exemplos:\n"
            ."job_title tem a resposta other: mostrado quando o visitante escolhe Outro.\n"
            ."country tem a resposta pt, es: mostrado com qualquer das duas.\n"
            ."age tem a resposta >= 18: mostrado a partir de 18. Também >, <, <= e != (exceto esta resposta).\n"
            .'newsletter tem a resposta yes: mostrado quando a caixa está assinalada.',
    'Fields' => 'Campos',
    'Files' => 'Ficheiros',
    'For surveys of people who have an account on this site, such as a course\'s participants: each account answers once, and visitors who aren\'t signed in see a sign-in link instead of the form. The site needs its own sign-in for visitors; without one, only people who sign in to the admin could answer.' => 'Para inquéritos a pessoas com conta neste site, como os participantes de um curso: cada conta responde uma vez, e os visitantes sem sessão iniciada veem uma ligação para entrar em vez do formulário. O site precisa de uma entrada própria para visitantes; sem ela, só as pessoas que entram na administração podem responder.',
    'Form' => 'Formulário',
    'Forms' => 'Formulários',
    'Help text' => 'Texto de ajuda',
    'Label' => 'Rótulo',
    'Largest file (KB)' => 'Tamanho máximo do ficheiro (KB)',
    'Long text' => 'Texto longo',
    'Maximum (characters, or the largest number)' => 'Máximo (caracteres, ou o maior número)',
    'Message after sending' => 'Mensagem depois de enviar',
    'Message when sending fails' => 'Mensagem quando o envio falha',
    'Most files' => 'Máximo de ficheiros',
    'Most rows' => 'Máximo de linhas',
    'Name (for developers)' => 'Nome (para programadores)',
    'New step' => 'Novo passo',
    'Number' => 'Número',
    'Options' => 'Opções',
    'Phone' => 'Telefone',
    'Repeating group (indent its fields under it)' => 'Grupo repetido (recue os campos abaixo dele)',
    'Required' => 'Obrigatório',
    'Send submissions to (emails, comma separated)' => 'Enviar respostas para (emails, separados por vírgulas)',
    'Show only when the field' => 'Mostrar só quando o campo',
    'Show this field only for some answers to another field. Leave both empty to always show it.' => 'Mostra este campo só com algumas respostas a outro campo. Deixe ambos vazios para o mostrar sempre.',
    'Signed-in visitors only, one answer each' => 'Só visitantes com sessão iniciada, uma resposta cada',
    'Text' => 'Texto',
    'Tick box' => 'Caixa de seleção',
    'Width (100, 50, 33 or 25)' => 'Largura (100, 50, 33 ou 25)',
    'has the answer' => 'tem a resposta',
    'Delete submissions after (days)' => 'Eliminar respostas após (dias)',
    'Every submission older than this is deleted with its files, whatever its status, by the daily purge. 365 keeps a year. Empty keeps them.' => 'Todas as respostas mais antigas do que isto são eliminadas com os seus ficheiros, qualquer que seja o estado, pela limpeza diária. 365 guarda um ano. Vazio guarda-as.',
    'Mail a daily summary instead of each submission' => 'Enviar um resumo diário em vez de cada resposta',
    'For busy forms: the people who receive submissions get one mail a day that lists the new ones.' => 'Para formulários com muitas respostas: quem as recebe tem um email por dia com as novas.',
];
