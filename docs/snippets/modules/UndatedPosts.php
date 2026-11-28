<?php

declare(strict_types=1);

namespace App\Insights;

use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;
use LynxCms\Insights\InsightText;

// #region source
/**
 * Blog posts without a "Published on" date. The blog lists posts by that date, so an undated
 * post sinks to the end. The plan sets each one to the day the post was created.
 */
final class UndatedPosts implements InsightSource
{
    public function key(): string
    {
        return 'undated-posts';
    }

    public function label(): string
    {
        return __('insights.undated_posts.label');
    }

    public function insights(InsightPeriod $period): array
    {
        $posts = lynx()->pages->find('template=post, published_on=, include=hidden');

        if ($posts->count() === 0) {
            return [];
        }

        $plan = [];

        foreach ($posts as $post) {
            $plan[] = ['action' => 'pages.save', 'arguments' => [
                'id' => $post->id,
                'values' => ['published_on' => substr((string) $post->createdAt, 0, 10)],
            ]];
        }

        return [new Insight(
            title: new InsightText('insights.undated_posts.title', ['count' => $posts->count()], $posts->count()),
            reason: new InsightText('insights.undated_posts.reason'),
            pages: $posts->ids(),
            plan: $plan,
            evidence: [['label' => new InsightText('insights.undated_posts.posts'), 'value' => $posts->count()]],
            weight: $posts->count(),
            subject: 'undated',
        )];
    }
}
// #endregion source
