<?php

declare(strict_types=1);

use App\Lynx\CanadianFrenchPlurals;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Search\Plurals\FrenchPlurals;
use LynxCms\Core\Search\Plurals\WordReducers;

/*
 * The plural reducer example in docs/basics/languages.md, from docs/snippets/advanced.
 */

require_once __DIR__.'/../../../docs/snippets/advanced/SwedishPlurals.php';

it('finds the singular from the plural in a language a site adds a reducer for', function (): void {
    courseSite();
    lynx()->languages->add('sv', 'Svenska', locale: 'sv-SE', fallback: 'default');
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true]]]);
    require __DIR__.'/../../../docs/snippets/advanced/plurals-register.php';
    ['alpha' => $alpha] = createCourses('alpha');
    lynx()->run(SavePage::class, ['id' => $alpha->id, 'values' => ['summary' => ['sv' => 'En bil och en flicka']]]);

    expect(lynx()->pages->find('search*="bilar flickor", include=all, language=sv')->map(fn ($page): string => (string) $page->name))->toBe(['alpha']);
});

require_once __DIR__.'/../../../docs/snippets/advanced/CanadianFrenchPlurals.php';

it('extends a built-in reducer for a regional locale', function (): void {
    require __DIR__.'/../../../docs/snippets/advanced/canadian-french-register.php';
    $canadian = app(WordReducers::class)->for(new Language(['code' => 'ca', 'locale' => 'fr-CA']));
    $french = app(WordReducers::class)->for(new Language(['code' => 'fr', 'locale' => 'fr-FR']));

    expect($canadian?->reduce('autobus'))->toBe('autobus')
        ->and($french?->reduce('autobus'))->toBe('autobu')
        ->and($canadian?->reduce('bateaux'))->toBe('bateau')
        ->and($french)->toBeInstanceOf(FrenchPlurals::class)
        ->and($french)->not->toBeInstanceOf(CanadianFrenchPlurals::class);
});
