<?php

declare(strict_types=1);

use App\Insights\UndatedPosts;
use Illuminate\Support\Facades\Lang;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\InsightReport;

/*
 * The Insights page's example source (docs/snippets/modules/UndatedPosts.php), on the blog of
 * "Your First Site": it reports the posts without a date, and approving dates them.
 */

require_once __DIR__.'/../../../docs/snippets/modules/UndatedPosts.php';

beforeEach(function (): void {
    installLynx();
    require __DIR__.'/../../../docs/snippets/first-site/schema.php';
    require __DIR__.'/../../../docs/snippets/first-site/pages.php';
    Lang::addLines(array_combine(
        array_map(fn (string $key): string => 'insights.undated_posts.'.$key, array_keys((require __DIR__.'/../../../docs/snippets/modules/lang/en/insights.php')['undated_posts'])),
        (require __DIR__.'/../../../docs/snippets/modules/lang/en/insights.php')['undated_posts'],
    ), 'en');
    // Only the example's source, as an app that registers it in its service provider.
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add(UndatedPosts::class);
});

it('reports the posts without a date', function (): void {
    $post = lynx()->pages->new('post', '/blog/');
    $post->name = 'undated';
    $post->title = 'An undated post';
    $post->save();

    $insight = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class)->insights()->sole();

    expect($insight->pages)->toContain($post->id)
        ->and($insight->title->translate())->not->toStartWith('insights.')
        ->and($insight->plan)->not->toBeEmpty();
});

it('dates the posts once approved', function (): void {
    $post = lynx()->pages->new('post', '/blog/');
    $post->name = 'undated';
    $post->title = 'An undated post';
    $post->save();
    $insight = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class)->insights()->sole();

    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);

    expect(lynx()->pages->get($post->id)?->getUnformatted('published_on'))->not->toBeNull()
        ->and($insight->fresh()?->state)->toBe(InsightState::Approved);
});
