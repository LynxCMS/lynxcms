<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\DeletePage;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Search\RebuildSearchIndex;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Search\Engines\LikeSearchEngine;
use LynxCms\Core\Search\Engines\SearchEngine;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [
        ['slug' => 'text', 'label' => 'Text', 'fields' => [['name' => 'body']]],
        ['slug' => 'aside', 'label' => 'Aside', 'fields' => [['name' => 'summary']]],
    ]]);
    lynx()->fields->add('level', 'choice', settings: ['options' => [
        ['value' => 'advanced', 'label' => ['default' => 'Advanced', 'pt' => 'Avançado']],
    ]]);
    lynx()->templates->update('course', ['add' => [['name' => 'blocks', 'searchable' => true], ['name' => 'level', 'searchable' => true]]]);
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
});

/**
 * Names of the pages a search finds.
 *
 * @return list<string>
 */
function searchNames(string $words, string $language = 'default'): array
{
    return lynx()->pages->find('search*="'.$words.'", include=all, language='.$language)->map(fn ($page): string => (string) $page->name);
}

function saveValues(int $id, array $values): void
{
    lynx()->run(SavePage::class, ['id' => $id, 'values' => $values]);
}

it('finds a page by text inside a matrix item, case and accent blind', function (): void {
    saveValues($this->alpha->id, ['blocks' => [['type' => 'text', 'values' => ['body' => ['default' => '<p>Filmado ao longo das quatro estações.</p>']]]]]);

    expect(searchNames('Filmado ao longo das quatro estações'))->toBe(['alpha'])
        ->and(searchNames('QUATRO estacoes'))->toBe(['alpha'])
        ->and(searchNames('estaç'))->toBe(['alpha'])
        ->and(searchNames('quatro invernos'))->toBe([]);
});

it('indexes the title always and only the fields the template marks searchable', function (): void {
    saveValues($this->alpha->id, ['summary' => ['default' => 'A hidden summary']]);

    expect(searchNames('alpha'))->toBe(['alpha'])
        ->and(searchNames('hidden'))->toBe([]);

    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true]]]);

    expect(searchNames('hidden'))->toBe(['alpha']);
});

it('leaves out disabled items', function (): void {
    saveValues($this->alpha->id, ['blocks' => [['type' => 'text', 'enabled' => false, 'values' => ['body' => ['default' => 'Switched off']]]]]);

    expect(searchNames('switched'))->toBe([]);
});

it('searches each language with fallback to the default one', function (): void {
    saveValues($this->alpha->id, ['blocks' => [['type' => 'text', 'values' => ['body' => ['default' => 'Seasons', 'pt' => 'Estações']]]]]);
    saveValues($this->beta->id, ['blocks' => [['type' => 'text', 'values' => ['body' => ['default' => 'Seasons too']]]]]);

    expect(searchNames('estações', 'pt'))->toBe(['alpha'])
        ->and(searchNames('seasons', 'pt'))->toBe(['beta'])
        ->and(searchNames('estações'))->toBe([]);
});

it('indexes choice labels in each language', function (): void {
    saveValues($this->alpha->id, ['level' => 'advanced']);

    expect(searchNames('avançado', 'pt'))->toBe(['alpha'])
        ->and(searchNames('advanced'))->toBe(['alpha']);
});

it('keeps the index current when pages change or go', function (): void {
    saveValues($this->alpha->id, ['blocks' => [['type' => 'text', 'values' => ['body' => ['default' => 'Old words']]]]]);
    saveValues($this->alpha->id, ['blocks' => [['type' => 'text', 'values' => ['body' => ['default' => 'New words']]]]]);

    expect(searchNames('old'))->toBe([])
        ->and(searchNames('new words'))->toBe(['alpha']);

    lynx()->run(DeletePage::class, ['page' => $this->alpha->id]);

    expect(searchNames('new words'))->toBe([]);
});

it('indexes pages in a language added later', function (): void {
    lynx()->languages->add('es', 'Español', fallback: 'default');

    expect(searchNames('alpha', 'es'))->toBe(['alpha']);
});

it('follows a changed language fallback', function (): void {
    saveValues($this->alpha->id, ['blocks' => [['type' => 'text', 'values' => ['body' => ['default' => 'Seasons', 'pt' => 'Estações']]]]]);
    lynx()->languages->add('es', 'Español', fallback: 'pt');
    $before = searchNames('estações', 'es');

    lynx()->languages->update('es', ['fallback' => '']);

    expect($before)->toBe(['alpha'])
        ->and(searchNames('estações', 'es'))->toBe([])
        ->and(searchNames('seasons', 'es'))->toBe(['alpha']);
});

it('rebuilds the index with search.rebuild', function (): void {
    Event::fakeFor(fn () => saveValues($this->alpha->id, ['blocks' => [['type' => 'text', 'values' => ['body' => ['default' => 'Rebuilt']]]]]));

    $dry = lynx()->run(RebuildSearchIndex::class, [], dryRun: true);

    expect($dry->changes->all())->not->toBeEmpty()
        ->and(searchNames('rebuilt'))->toBe([]);

    lynx()->run(RebuildSearchIndex::class, []);

    expect(searchNames('rebuilt'))->toBe(['alpha']);
});

it('refuses other operators on search', function (): void {
    expect(fn () => lynx()->pages->find('search=alpha'))->toThrow(SelectorException::class);
});

it('reserves the search field name', function (): void {
    expect(fn () => lynx()->fields->add('search', 'text'))->toThrow(ValidationException::class);
});

it('matches word beginnings with LIKE on databases without a known full text engine', function (): void {
    app()->bind(SearchEngine::class, fn (): SearchEngine => new LikeSearchEngine(DB::connection()));
    saveValues($this->alpha->id, ['blocks' => [['type' => 'text', 'values' => ['body' => ['default' => 'Quatro estações']]]]]);

    expect(searchNames('estac quatro'))->toBe(['alpha'])
        ->and(searchNames('tro'))->toBe([]);
});

it('rebuilds only the pages of one template, leaving the rest as they are', function (): void {
    Event::fakeFor(function (): void {
        saveValues($this->alpha->id, ['blocks' => [['type' => 'text', 'values' => ['body' => ['default' => 'Rebuilt']]]]]);
        saveValues(lynx()->pages->get('/courses/')->id, ['title' => ['default' => 'Catalogue']]);
    });

    $result = lynx()->run(RebuildSearchIndex::class, ['template' => 'course']);

    expect($result->arrayValue())->toBe(['pages' => 2])
        ->and(searchNames('rebuilt'))->toBe(['alpha'])
        ->and(searchNames('catalogue'))->toBe([])
        ->and(searchNames('courses'))->toBe(['courses']);
});

it('rebuilds one template from the command line', function (): void {
    Event::fakeFor(fn () => saveValues($this->alpha->id, ['blocks' => [['type' => 'text', 'values' => ['body' => ['default' => 'Rebuilt']]]]]));

    $this->artisan('lynx:search-index', ['--template' => 'course'])
        ->expectsOutputToContain('Indexed 2 page(s).')
        ->assertSuccessful();

    expect(searchNames('rebuilt'))->toBe(['alpha']);
});

it('refuses a template that does not exist', function (): void {
    expect(fn (): ActionResult => lynx()->run(RebuildSearchIndex::class, ['template' => 'nope']))->toThrow(InvalidArgumentException::class, 'Template [nope] does not exist.');
});

it('indexes email and url fields', function (): void {
    lynx()->fields->add('contact_email', 'email');
    lynx()->fields->add('website', 'url');
    lynx()->templates->update('course', ['add' => [['name' => 'contact_email', 'searchable' => true], ['name' => 'website', 'searchable' => true]]]);
    saveValues($this->alpha->id, ['contact_email' => 'maria@esferacubica.pt', 'website' => 'https://açores.pt/formação/']);

    expect(searchNames('maria'))->toBe(['alpha'])
        ->and(searchNames('esferacubica'))->toBe(['alpha'])
        ->and(searchNames('acores formacao'))->toBe(['alpha']);
});
