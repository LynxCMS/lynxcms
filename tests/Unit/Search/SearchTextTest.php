<?php

declare(strict_types=1);

use LynxCms\Core\Search\Plurals\PortuguesePlurals;
use LynxCms\Core\Search\SearchText;

it('follows each word with its reduced form when it differs', function (): void {
    expect(SearchText::terms('Livros sobre animais', new PortuguesePlurals))->toBe(['livros', 'livro', 'sobre', 'animais', 'animal']);
});

it('reduces before folding accents, so the rules read them', function (): void {
    expect(SearchText::terms('<p>Estações e PÃES</p>', new PortuguesePlurals))->toBe(['estacoes', 'estacao', 'e', 'paes', 'pao']);
});

it('keeps each term once', function (): void {
    expect(SearchText::terms('livro livros livro', new PortuguesePlurals))->toBe(['livro', 'livros']);
});

it('gives the folded words alone without a reducer', function (): void {
    expect(SearchText::terms('Livros sobre animais', null))->toBe(['livros', 'sobre', 'animais'])
        ->and(SearchText::words('Estações'))->toBe(['estacoes']);
});
