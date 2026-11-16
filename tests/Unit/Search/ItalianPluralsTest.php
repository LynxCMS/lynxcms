<?php

declare(strict_types=1);

use LynxCms\Core\Search\Plurals\ItalianPlurals;

it('gives singular and plural the same form', function (string $singular, string $plural): void {
    $reducer = new ItalianPlurals;

    expect($reducer->reduce($plural))->toBe($reducer->reduce($singular));
})->with([
    ['libro', 'libri'],
    ['casa', 'case'],
    ['cane', 'cani'],
    ['problema', 'problemi'],
    ['amico', 'amici'],
    ['lago', 'laghi'],
]);

it('leaves words that do not change in the plural as they are', function (string $word): void {
    expect((new ItalianPlurals)->reduce($word))->toBe($word);
})->with(['città', 'caffè', 'film', 'sport']);
