<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Translation\TranslationProviders;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Sources\MissingTranslations;
use LynxCms\Tests\Fixtures\FakeTranslationProvider;

/*
 * The missing translations source: published pages with no version in an active language, one
 * insight per language, the most linked pages first. With a translation provider, approving
 * translates them into their drafts.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta, 'gamma' => $this->gamma] = createCourses('alpha', 'beta', 'gamma');
    $this->provider = new FakeTranslationProvider;
    app()->instance(TranslationProvider::class, $this->provider);
});

function missingTranslations(): array
{
    return app(MissingTranslations::class)->insights(InsightPeriod::lastDays(30));
}

it('reports the pages with no version in a language, most linked first', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => ['related' => $this->gamma->id]]);
    lynx()->run(SavePage::class, ['id' => $this->beta->id, 'values' => ['related' => $this->gamma->id]]);
    lynx()->run(SavePage::class, ['id' => $this->gamma->id, 'values' => ['related' => $this->beta->id]]);

    [$insight] = missingTranslations();

    expect($insight->subject)->toBe('pt')
        ->and($insight->pages)->toBe([$this->gamma->id, $this->beta->id, $this->alpha->id])
        ->and($insight->evidence[0]['value'])->toBe(3)
        ->and($insight->evidence[1]['value'])->toBe(3);
});

it('leaves out pages named in the language', function (): void {
    $this->alpha->setLanguageName('pt', 'alfa');
    $this->alpha->save();

    expect(missingTranslations()[0]->pages)->not->toContain($this->alpha->id);
});

it('leaves out unpublished pages and the default language', function (): void {
    lynx()->pages->setStatus($this->alpha, add: ['unpublished']);

    $insights = missingTranslations();

    expect($insights)->toHaveCount(1)
        ->and($insights[0]->pages)->not->toContain($this->alpha->id);
});

it('names at most 20 pages', function (): void {
    createCourses(...array_map(fn (int $n): string => "course-{$n}", range(1, 25)));

    expect(missingTranslations()[0]->pages)->toHaveCount(MissingTranslations::PAGES);
});

it('reports nothing when every page has every language', function (): void {
    foreach ([$this->alpha, $this->beta, $this->gamma] as $page) {
        $page->setLanguageName('pt', $page->name().'-pt');
        $page->save();
    }

    expect(missingTranslations())->toBe([]);
});

it('only informs without a translation provider', function (): void {
    $source = new MissingTranslations(app(LanguageRegistry::class), app(PageFinder::class), new TranslationProviders(new Container));

    expect($source->insights(InsightPeriod::lastDays(30))[0]->plan)->toBe([]);
});

it('translates the pages into their drafts once approved', function (): void {
    $insight = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class)->insights()->where('source', 'missing-translations')->sole();
    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);

    expect($this->provider->calls)->not->toBeEmpty()
        ->and(lynx()->pages->draft($this->alpha->id)?->getLanguageValue('pt', 'title'))->toBe('[pt-PT] Alpha');
});
