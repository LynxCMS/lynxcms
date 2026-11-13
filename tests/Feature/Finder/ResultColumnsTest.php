<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Finder\Actions\SaveSearch;
use LynxCms\Finder\Admin\FinderScreen;
use LynxCms\Finder\Catalog\ConditionCatalog;
use LynxCms\Finder\Catalog\Subject;
use LynxCms\Finder\Models\SavedSearch;
use LynxCms\Finder\Search\FinderQuery;

/*
 * Field columns in the finder's results: the search keeps the fields chosen as columns (in its
 * link, its saved copy and its CSV export), and the table shows each as short text.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('featured', 'toggle');
    lynx()->fields->add('gallery', 'images');
    lynx()->templates->update('course', ['add' => [['name' => 'featured'], ['name' => 'gallery']]]);
    $this->courses = createCourses('alpha', 'beta');
    $this->courses['alpha']->price = 10;
    $this->courses['alpha']->featured = true;
    $this->courses['alpha']->save();
    $this->courses['beta']->price = 60;
    $this->courses['beta']->related = $this->courses['alpha'];
    $this->courses['beta']->save();
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('keeps the chosen columns once each, up to the limit', function (): void {
    $columns = ['price', 'price', ...array_map(fn (int $n): string => "field_{$n}", range(1, FinderQuery::MAX_COLUMNS))];
    $query = FinderQuery::fromArray(['columns' => $columns]);

    expect($query->columns)->toHaveCount(FinderQuery::MAX_COLUMNS)
        ->and($query->columns[0])->toBe('price')
        ->and(FinderQuery::fromArray($query->toArray())->columns)->toBe($query->columns);
});

it('offers the fields that read as short text', function (): void {
    $columns = array_map(fn (Subject $subject): string => $subject->key, app(ConditionCatalog::class)->columns());

    expect($columns)->toContain('price', 'summary', 'featured', 'related')
        ->not->toContain('gallery', 'template', 'status');
});

it('shows a column for each chosen field', function (): void {
    $beta = $this->courses['beta']->id;

    Livewire::test(FinderScreen::class)
        ->set('search.columns', ['price', 'related', 'featured'])
        ->assertSeeHtml('data-finder-column="price"')
        ->assertSeeHtml('data-finder-cell="'.$beta.'-price">60<')
        ->assertSeeHtml('data-finder-cell="'.$beta.'-related">Alpha<')
        ->assertSeeHtml('data-finder-cell="'.$this->courses['alpha']->id.'-featured">'.__('lynx-admin::editor.value.yes').'<');
});

it('shows no column for fields it does not offer', function (): void {
    Livewire::test(FinderScreen::class)
        ->set('search.columns', ['gallery', 'nope'])
        ->assertDontSeeHtml('data-finder-column=');
});

it('keeps the page of results when the columns change', function (): void {
    createCourses(...array_map(fn (int $n): string => "extra-{$n}", range(1, 30)));

    Livewire::test(FinderScreen::class)
        ->call('next')
        ->set('search.columns', ['price'])
        ->assertSet('start', 25);
});

it('exports the chosen columns', function (): void {
    $search = ['groups' => [['conditions' => [['subject' => 'template', 'operator' => 'is', 'value' => 'course']]]], 'columns' => ['price']];

    $csv = $this->get(route('lynx.admin.finder.export', ['q' => $search]))->assertOk()->streamedContent();

    expect(strtok($csv, "\n"))->toEndWith(','.app(ConditionCatalog::class)->find('price')?->label)
        ->and($csv)->toContain(",60\n");
});

it('saves the chosen columns with the search', function (): void {
    $saved = lynx()->run(SaveSearch::class, ['name' => 'Prices', 'query' => ['columns' => ['price']]])->valueAs(SavedSearch::class);

    expect(FinderQuery::fromArray($saved->query)->columns)->toBe(['price']);
});
