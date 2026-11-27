<?php

declare(strict_types=1);

return [
    'title' => 'Sugestões',
    'description' => 'Alterações sugeridas a partir do que está no site. Reveja um plano e depois aprove-o, arquive-o até ao próximo relatório ou dispense-o.',
    'home_stat' => 'Sugestões em aberto',
    'generate' => 'Escrever um relatório agora',
    'generated' => '{0} O relatório não tem sugestões.|{1} O relatório tem 1 sugestão.|[2,*] O relatório tem :count sugestões.',
    'period' => ':start a :end',
    'failed' => 'A fonte :source falhou: :error',
    'empty' => [
        'title' => 'Ainda não há relatórios',
        'help' => 'Os relatórios são escritos no horário definido em LYNX_INSIGHTS_SCHEDULE, ou com lynx:insights.',
    ],
    'none' => [
        'title' => 'Nada a sugerir',
        'help' => 'O último relatório não encontrou alterações a sugerir.',
    ],
    'earlier' => 'Relatórios anteriores',
    'states' => [
        'open' => '{1} 1 em aberto|[0,*] :count em aberto',
        'approved' => '{1} 1 aprovada|[0,*] :count aprovadas',
        'dismissed' => '{1} 1 dispensada|[0,*] :count dispensadas',
        'archived' => '{1} 1 arquivada|[0,*] :count arquivadas',
        'superseded' => '{1} 1 substituída|[0,*] :count substituídas',
    ],
    'state' => [
        'open' => 'Em aberto',
        'approved' => 'Aprovada',
        'dismissed' => 'Dispensada',
        'archived' => 'Arquivada',
        'superseded' => 'Substituída',
    ],
    'review' => [
        'title' => 'Aprovar faz estas alterações:',
        'open' => 'Rever alterações',
        'close' => 'Fechar revisão',
    ],
    'approve' => 'Aprovar',
    'archive' => 'Arquivar',
    'dismiss' => 'Dispensar',
    'approved' => 'Sugestão aprovada. As alterações foram guardadas.',
    'archived' => 'Sugestão arquivada. O próximo relatório volta a mostrá-la com novos dados.',
    'dismissed' => 'Sugestão dispensada.',
];
