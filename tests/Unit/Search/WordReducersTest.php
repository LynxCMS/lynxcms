<?php

declare(strict_types=1);

use LynxCms\Core\Models\Language;
use LynxCms\Core\Search\Plurals\BrazilianPortuguesePlurals;
use LynxCms\Core\Search\Plurals\EnglishPlurals;
use LynxCms\Core\Search\Plurals\PortuguesePlurals;
use LynxCms\Core\Search\Plurals\WordReducer;
use LynxCms\Core\Search\Plurals\WordReducers;

function languageWith(string $code, ?string $locale = null): Language
{
    return new Language(['code' => $code, 'locale' => $locale]);
}

function shoutingReducer(): WordReducer
{
    return new class implements WordReducer
    {
        public function reduce(string $word): string
        {
            return 'reduced';
        }
    };
}

it('finds a reducer by the full locale, then by the bare language', function (): void {
    $reducers = app(WordReducers::class);

    expect($reducers->for(languageWith('pt', 'pt-PT')))->toBeInstanceOf(PortuguesePlurals::class)
        ->and($reducers->for(languageWith('br', 'pt_BR')))->toBeInstanceOf(BrazilianPortuguesePlurals::class)
        ->and($reducers->for(languageWith('en')))->toBeInstanceOf(EnglishPlurals::class);
});

it('has reducers for the main European languages and Brazilian Portuguese', function (string $locale): void {
    expect(app(WordReducers::class)->for(languageWith('x', $locale)))->not->toBeNull();
})->with(['pt-BR', 'pt-PT', 'en', 'fr', 'es', 'it', 'de', 'nl']);

it('gives Brazilian Portuguese its own reducer', function (): void {
    $reducer = app(WordReducers::class)->for(languageWith('br', 'pt-BR'));

    expect($reducer)->toBeInstanceOf(BrazilianPortuguesePlurals::class)
        ->and($reducer?->reduce('trens'))->toBe('trem')
        ->and($reducer?->reduce('ônibus'))->toBe('ônibus')
        ->and($reducer?->reduce('contatos'))->toBe('contato');
});

it('prefers a reducer registered for the full locale', function (): void {
    $reducers = app(WordReducers::class);
    $reducers->register('pt-BR', shoutingReducer());

    expect($reducers->for(languageWith('br', 'pt-BR'))?->reduce('livros'))->toBe('reduced')
        ->and($reducers->for(languageWith('pt', 'pt-PT')))->toBeInstanceOf(PortuguesePlurals::class);
});

it('lets a site replace a built-in reducer', function (): void {
    app(WordReducers::class)->register('pt', shoutingReducer());

    expect(app(WordReducers::class)->for(languageWith('pt'))?->reduce('livros'))->toBe('reduced');
});

it('gives the default language the reducer of lynx.base_locale', function (): void {
    config(['lynx.base_locale' => 'pt-BR']);

    expect(app(WordReducers::class)->for(languageWith('default')))->toBeInstanceOf(PortuguesePlurals::class);
});

it('has no reducer for a language it does not know, or when switched off', function (): void {
    expect(app(WordReducers::class)->for(languageWith('fi')))->toBeNull();

    config(['lynx.search.reduce_plurals' => false]);

    expect(app(WordReducers::class)->for(languageWith('pt')))->toBeNull();
});
