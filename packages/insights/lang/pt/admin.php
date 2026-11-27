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
    'filters' => [
        'search' => 'Pesquisar',
        'search_placeholder' => 'Título, motivo, página',
        'state' => 'Mostrar',
        'states' => [
            'open' => 'Em aberto (:count)',
            'approved' => 'Aprovadas (:count)',
            'dismissed' => 'Dispensadas (:count)',
            'archived' => 'Arquivadas (:count)',
            'superseded' => 'Substituídas (:count)',
            'all' => 'Todas (:count)',
        ],
        'source' => 'Fonte',
        'all_sources' => 'Todas as fontes',
        'report' => 'Relatório',
        'latest' => 'Último: :start a :end',
        'clear' => 'Limpar filtros',
    ],
    'no_match' => [
        'title' => 'Nenhuma sugestão corresponde',
        'help' => 'Altere a pesquisa ou os filtros, ou limpe-os.',
    ],
    'pages' => '{1} 1 página|[2,*] :count páginas',
    'more_pages' => '{1} Mostrar mais 1 página|[2,*] Mostrar mais :count páginas',
    'state' => [
        'open' => 'Em aberto',
        'running' => 'Em curso',
        'approved' => 'Aprovada',
        'dismissed' => 'Dispensada',
        'archived' => 'Arquivada',
        'superseded' => 'Substituída',
    ],
    'progress' => ':done de :total alterações feitas',
    'progress_label' => 'Progresso da aprovação',
    'failed_steps' => '{1} 1 alteração falhou, :done feitas. Aprove novamente para a repetir.|[2,*] :count alterações falharam, :done feitas. Aprove novamente para as repetir.',
    'steps' => [
        'step' => 'Alteração :number',
        'pending' => 'Em espera',
        'done' => 'Feita',
        'failed' => 'Falhou',
    ],
    'review' => [
        'title' => 'Aprovar faz estas alterações:',
        'open' => 'Rever alterações',
        'close' => 'Fechar revisão',
    ],
    'approve' => 'Aprovar',
    'archive' => 'Arquivar',
    'dismiss' => 'Dispensar',
    'started' => 'Aprovação iniciada. As alterações são feitas uma a uma, e esta página mostra o progresso.',
    'archived' => 'Sugestão arquivada. O próximo relatório volta a mostrá-la com novos dados.',
    'dismissed' => 'Sugestão dispensada.',
];
