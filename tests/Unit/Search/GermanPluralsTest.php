<?php

declare(strict_types=1);

use LynxCms\Core\Search\Plurals\GermanPlurals;
use LynxCms\Core\Search\SearchText;

it('gives singular and plural the same form, umlauts folded', function (string $singular, string $plural): void {
    $reducer = new GermanPlurals;

    expect(SearchText::fold($reducer->reduce($plural)))->toBe(SearchText::fold($reducer->reduce($singular)));
})->with([
    ['tag', 'tage'],
    ['kind', 'kinder'],
    ['frau', 'frauen'],
    ['blume', 'blumen'],
    ['haus', 'häuser'],
    ['buch', 'bücher'],
    ['apfel', 'äpfel'],
    ['auto', 'autos'],
    ['lehrerin', 'lehrerinnen'],
    ['garten', 'gärten'],
]);

it('keeps words ending in ss or a consonant and s', function (string $word): void {
    expect((new GermanPlurals)->reduce($word))->toBe($word);
})->with(['fluss', 'haus', 'kurs']);
