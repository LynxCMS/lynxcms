<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Pages\SavePage;

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
