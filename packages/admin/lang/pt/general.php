<?php

declare(strict_types=1);

return [
    'title' => 'Geral',
    'description' => 'Como a administração se apresenta a quem inicia sessão.',
    'not_found' => [
        'heading' => 'Página não encontrada',
        'help' => 'A página mostrada, com o estado 404, quando um endereço não corresponde a nenhuma página. Os editores escrevem-na como qualquer outra. Sem ela, o site mostra o 404 simples.',
        'search' => 'Pesquise uma página',
        'not_shown' => 'Essa página não está publicada ou está no lixo, por isso é mostrado o 404 simples.',
        'save' => 'Guardar',
        'edit' => 'Editar a página',
        'denied' => 'Só os superutilizadores escolhem a página não encontrada.',
        'saved' => 'Guardado.',
        'saved_plain' => 'Guardado. As páginas em falta mostram o 404 simples.',
    ],
    'branding' => [
        'heading' => 'Marca',
        'intro' => 'O nome, o logótipo e as cores do site nos ecrãs de início de sessão e no topo da barra lateral. Deixe um campo vazio para usar a predefinição.',
        'name' => 'Nome',
        'name_help' => 'Mostrado junto à inicial quando não há logótipo, nos separadores do navegador, e lido em vez do logótipo.',
        'logo' => 'Logótipo',
        'logo_help' => 'PNG, JPEG, WebP ou SVG, até 1 MB. Um logótipo largo com o nome funciona melhor; substitui o nome.',
        'remove_logo' => 'Remover o logótipo carregado',
        'remove_logo_configured' => 'Remover o logótipo carregado (volta ao configurado)',
        'background' => 'Fundo do início de sessão',
        'background_help' => 'Por trás do cartão de início de sessão. Em #rrggbb.',
        'accent' => 'Cor de destaque',
        'accent_help' => 'Botões, ligações e anéis de foco. Em #rrggbb.',
        'pick' => ':color: escolher',
        'default_placeholder' => 'padrão',
        'default' => 'Predefinição',
        'save' => 'Guardar',
        'preview_label' => 'Pré-visualização do ecrã de início de sessão',
        'preview' => 'Pré-visualização',
        'svg_note' => 'O novo logótipo SVG aparece depois de guardado.',
        'denied' => 'Só os superutilizadores alteram a marca.',
        'saved' => 'Guardado. Os ecrãs de início de sessão e a barra lateral mostram-no a partir da página seguinte.',
    ],
];
