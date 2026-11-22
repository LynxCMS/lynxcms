<?php

declare(strict_types=1);

return [
    'denied' => [
        'anywhere' => 'Não tem a permissão [:permission] (:label).',
        'template' => 'Não tem a permissão [:permission] (:label) para o modelo [:template].',
        'page' => 'Não tem a permissão [:permission] (:label) na página [:page].',
        'action' => 'Não tem a permissão [:permissions], de que [:action] precisa.',
        'superusers' => 'Só os superutilizadores podem executar [:action].',
    ],
    'last_superuser' => 'Isto removeria o último superutilizador. Dê primeiro uma função de superutilizador a outra pessoa.',
    'schema_editing' => '[:action] altera o esquema, e a edição do esquema está desligada neste site (lynx.schema_editing): o esquema vem de ficheiros. Altere lynx/schema e execute php artisan lynx:schema:apply.',
    'agent_languages' => 'Este agente não pode escrever em: :languages.',
    'providers' => [
        'unknown' => 'Não há nenhum fornecedor de tradução com o nome [:provider].',
        'none' => 'Não há nenhum fornecedor de tradução configurado. Instale lynxcms/ai ou um módulo de tradução.',
    ],
    'roles' => [
        'missing' => 'A função [:role] não existe.',
        'unknown' => 'Função desconhecida: [:roles].',
        'needs' => '[:permission] precisa de [:permissions] na mesma função.',
        'unknown_permission' => 'Permissão desconhecida: [:permission].',
        'not_scoped' => '[:permission] não pode ser limitada a modelos ou ramos.',
        'unknown_template' => 'Modelo desconhecido: [:templates].',
        'unknown_page' => 'Página desconhecida: [:pages].',
    ],
];
