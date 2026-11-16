<?php

declare(strict_types=1);

use LynxCms\Core\Search\Plurals\FrenchPlurals;

it('turns a plural into its singular', function (string $plural, string $singular): void {
    expect((new FrenchPlurals)->reduce($plural))->toBe($singular);
})->with([
    ['bateaux', 'bateau'],
    ['journaux', 'journal'],
    ['chevaux', 'cheval'],
    ['jeux', 'jeu'],
    ['bijoux', 'bijou'],
    ['maisons', 'maison'],
    ['travaux', 'travail'],
]);

it('leaves singulars and words that only look plural as they are', function (string $word): void {
    expect((new FrenchPlurals)->reduce($word))->toBe($word);
})->with(['maison', 'mais', 'fois', 'mois', 'cours', 'prix', 'faux']);
