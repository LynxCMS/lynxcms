<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Search\SearchIndexReport;

beforeEach(function (): void {
    courseSite();
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true], ['name' => 'related', 'searchable' => true]]]);
    ['alpha' => $this->alpha] = createCourses('alpha');
});

it('counts the indexed pages of each language and says which reduce plurals', function (): void {
    $languages = collect(app(SearchIndexReport::class)->languages())->keyBy('code');

    expect($languages['pt']['plurals'])->toBeTrue()
        ->and($languages['pt']['rows'])->toBeGreaterThan(0)->toBe($languages['pt']['pages'])
        ->and($languages['default']['rows'])->toBe($languages['default']['pages']);
});

it('lists the fields each template indexes: the title and searchable fields that give text', function (): void {
    $course = collect(app(SearchIndexReport::class)->templates())->firstWhere('name', 'course');

    expect($course)->toMatchArray(['fields' => ['title', 'summary'], 'pages' => 1, 'has_url' => true]);
});

it('shows what a page\'s rows hold in each language', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => ['summary' => ['pt' => 'Dois livros']]]);

    expect(app(SearchIndexReport::class)->page($this->alpha->id))->toBe([
        'default' => 'alpha',
        'pt' => 'alpha dois livros livro',
    ]);
});
