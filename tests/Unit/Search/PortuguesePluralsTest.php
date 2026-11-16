<?php

declare(strict_types=1);

use LynxCms\Core\Search\Plurals\PortuguesePlurals;

it('turns a plural into its singular', function (string $plural, string $singular): void {
    expect((new PortuguesePlurals)->reduce($plural))->toBe($singular);
})->with([
    'ões' => ['estações', 'estação'],
    'ães' => ['pães', 'pão'],
    'ãos' => ['mãos', 'mão'],
    'oes without the accent' => ['estacoes', 'estacao'],
    'aes without the accent' => ['paes', 'pao'],
    'ais' => ['animais', 'animal'],
    'ais again' => ['jornais', 'jornal'],
    'éis' => ['papéis', 'papel'],
    'eis' => ['fáceis', 'fácil'],
    'eis again' => ['úteis', 'útil'],
    'óis' => ['lençóis', 'lençol'],
    'uis' => ['azuis', 'azul'],
    'is' => ['funis', 'funil'],
    'is again' => ['barris', 'barril'],
    'ns' => ['homens', 'homem'],
    'ns again' => ['jardins', 'jardim'],
    'res' => ['flores', 'flor'],
    'zes' => ['luzes', 'luz'],
    'eses' => ['meses', 'mês'],
    'eses again' => ['ingleses', 'inglês'],
    'les keeps the e' => ['controles', 'controle'],
    'ses keeps the e' => ['frases', 'frase'],
    's' => ['livros', 'livro'],
    's after an accent' => ['cafés', 'café'],
]);

it('leaves singulars, invariable words and short words as they are', function (string $word): void {
    expect((new PortuguesePlurals)->reduce($word))->toBe($word);
})->with(['livro', 'animal', 'estação', 'lápis', 'vírus', 'ônibus', 'mais', 'mes', 'sol']);

it('maps irregular plurals and the plurals the rules get wrong', function (string $plural, string $singular): void {
    expect((new PortuguesePlurals)->reduce($plural))->toBe($singular);
})->with([
    ['mães', 'mãe'],
    ['maes', 'mae'],
    ['países', 'país'],
    ['males', 'mal'],
    ['torres', 'torre'],
    ['pais', 'pai'],
]);
