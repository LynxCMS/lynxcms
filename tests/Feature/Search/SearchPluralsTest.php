<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Search\RebuildSearchIndex;
use LynxCms\Core\Search\Engines\LikeSearchEngine;
use LynxCms\Core\Search\Engines\SearchEngine;
use LynxCms\Core\Search\SearchText;

/*
 * Search finds singular and plural forms (ADR-0119), here in Portuguese (the pt language, pt-PT).
 */

beforeEach(function (): void {
    courseSite();
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true]]]);
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
});

/** @return list<string> names of the pages a search in $language finds */
function pluralSearch(string $words, string $language = 'pt'): array
{
    return lynx()->pages->find('search*="'.$words.'", include=all, language='.$language)->map(fn ($page): string => (string) $page->name);
}

function saySummary(int $id, string $summary, string $language = 'pt'): void
{
    lynx()->run(SavePage::class, ['id' => $id, 'values' => ['summary' => [$language => $summary]]]);
}

it('finds the singular from the plural and the plural from the singular', function (): void {
    saySummary($this->alpha->id, 'Um livro sobre o mar');
    saySummary($this->beta->id, 'Dois livros sobre o mar');

    expect(pluralSearch('livros'))->toBe(['alpha', 'beta'])
        ->and(pluralSearch('livro'))->toBe(['alpha', 'beta']);
});

it('finds plurals that change the end of the word, with or without accents', function (string $singular, string $plural): void {
    saySummary($this->alpha->id, "Texto com {$singular}");
    saySummary($this->beta->id, "Texto com {$plural}");

    expect(pluralSearch($singular))->toBe(['alpha', 'beta'])
        ->and(pluralSearch($plural))->toBe(['alpha', 'beta'])
        ->and(pluralSearch(SearchText::fold($plural)))->toBe(['alpha', 'beta']);
})->with([
    ['animal', 'animais'],
    ['estação', 'estações'],
    ['pão', 'pães'],
    ['homem', 'homens'],
]);

it('still matches word beginnings while typing', function (): void {
    saySummary($this->alpha->id, 'As quatro estações');

    expect(pluralSearch('estaç'))->toBe(['alpha']);
});

it('never reduces short words', function (): void {
    saySummary($this->alpha->id, 'Fala comigo e me diz');

    expect(pluralSearch('mes'))->toBe([]);
});

it('maps an irregular plural to its own singular only', function (): void {
    saySummary($this->alpha->id, 'A mãe');
    saySummary($this->beta->id, 'A mão');

    expect(pluralSearch('mães'))->toBe(['alpha']);
});

it('does not reduce in a language without a reducer', function (): void {
    lynx()->languages->add('fi', 'Suomi', locale: 'fi', fallback: 'default');
    saySummary($this->alpha->id, 'livro', 'fi');

    expect(pluralSearch('livros', 'fi'))->toBe([]);
});

it('does not reduce when switched off', function (): void {
    config(['lynx.search.reduce_plurals' => false]);
    saySummary($this->alpha->id, 'Um livro');

    expect(pluralSearch('livros'))->toBe([]);
});

it('finds fallback text by its own words', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => ['summary' => ['default' => 'Shipping containers']]]);

    expect(pluralSearch('containers'))->toBe(['alpha'])
        ->and(pluralSearch('shipping'))->toBe(['alpha']);
});

it('keeps finding what an older index found, and finds plurals once rebuilt', function (): void {
    config(['lynx.search.reduce_plurals' => false]);
    saySummary($this->alpha->id, 'Os animais do livro');
    config(['lynx.search.reduce_plurals' => true]);

    expect(pluralSearch('animais'))->toBe(['alpha'])
        ->and(pluralSearch('livros'))->toBe(['alpha'])
        ->and(pluralSearch('animal'))->toBe([]);

    lynx()->run(RebuildSearchIndex::class, []);

    expect(pluralSearch('animal'))->toBe(['alpha']);
});

it('finds plurals with LIKE on databases without a known full text engine', function (): void {
    app()->bind(SearchEngine::class, fn (): SearchEngine => new LikeSearchEngine(DB::connection()));
    saySummary($this->alpha->id, 'Os animais');
    saySummary($this->beta->id, 'Um livro');

    expect(pluralSearch('animal'))->toBe(['alpha'])
        ->and(pluralSearch('livros'))->toBe(['beta'])
        ->and(pluralSearch('anim'))->toBe(['alpha'])
        ->and(pluralSearch('livr mar'))->toBe([]);
});

it('finds singular and plural in the other built-in languages', function (string $locale, string $singular, string $plural): void {
    lynx()->languages->add('xx', 'Test', locale: $locale, fallback: 'default');
    saySummary($this->alpha->id, $singular, 'xx');
    saySummary($this->beta->id, $plural, 'xx');

    expect(pluralSearch($singular, 'xx'))->toBe(['alpha', 'beta'])
        ->and(pluralSearch($plural, 'xx'))->toBe(['alpha', 'beta']);
})->with([
    'Brazilian Portuguese' => ['pt-BR', 'trem', 'trens'],
    'Spanish' => ['es', 'canción', 'canciones'],
    'Italian' => ['it', 'libro', 'libri'],
    'German' => ['de', 'Haus', 'Häuser'],
    'Dutch' => ['nl', 'kat', 'katten'],
]);
