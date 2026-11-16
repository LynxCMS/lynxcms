<?php

declare(strict_types=1);

use LynxCms\Core\Search\Plurals\SpanishPlurals;
use LynxCms\Core\Search\SearchText;

it('turns a plural into its singular, accents aside', function (string $plural, string $singular): void {
    expect(SearchText::fold((new SpanishPlurals)->reduce($plural)))->toBe(SearchText::fold($singular));
})->with([
    'vowel' => ['libros', 'libro'],
    'e kept' => ['noches', 'noche'],
    'consonant cluster with re' => ['padres', 'padre'],
    'r' => ['flores', 'flor'],
    'l' => ['árboles', 'árbol'],
    'n' => ['jóvenes', 'joven'],
    'ón' => ['canciones', 'canción'],
    'd' => ['ciudades', 'ciudad'],
    'z' => ['luces', 'luz'],
    'ce kept' => ['dulces', 'dulce'],
    'y' => ['reyes', 'rey'],
    'és' => ['ingleses', 'inglés'],
    'lle' => ['calles', 'calle'],
    'aje' => ['viajes', 'viaje'],
]);

it('leaves singulars and invariable words as they are', function (string $word): void {
    expect((new SpanishPlurals)->reduce($word))->toBe($word);
})->with(['libro', 'lunes', 'crisis', 'análisis', 'virus']);
