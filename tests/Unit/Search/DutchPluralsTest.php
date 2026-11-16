<?php

declare(strict_types=1);

use LynxCms\Core\Search\Plurals\DutchPlurals;

it('turns a plural into its singular', function (string $plural, string $singular): void {
    expect((new DutchPlurals)->reduce($plural))->toBe($singular);
})->with([
    ['boeken', 'boek'],
    ['katten', 'kat'],
    ['huizen', 'huis'],
    ['brieven', 'brief'],
    ['kinderen', 'kind'],
    ['tafels', 'tafel'],
    ['kamers', 'kamer'],
    ['meisjes', 'meisje'],
    ['bomen', 'boom'],
    ['jaren', 'jaar'],
]);

it('leaves singulars as they are', function (string $word): void {
    expect((new DutchPlurals)->reduce($word))->toBe($word);
})->with(['boek', 'huis', 'bus', 'tafel']);

it('lets a subclass add exceptions the spelling rules would otherwise change', function (): void {
    $flemish = new class extends DutchPlurals
    {
        protected function exceptions(): array
        {
            return ['bomen' => 'bomen'] + parent::exceptions();
        }
    };

    expect($flemish->reduce('bomen'))->toBe('bomen')
        ->and($flemish->reduce('katten'))->toBe('kat');
});
