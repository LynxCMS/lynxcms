<?php

declare(strict_types=1);

namespace App\Blog;

use LynxCms\Core\Pages\Page;

// #region checks
/**
 * What a blog post still needs before an editor publishes it. The admin's Review screen, the
 * post's Checklist tab and the MCP tool all ask this class, so they never disagree.
 */
final class PostChecks
{
    /** Search engines cut descriptions at about this many characters. */
    public const int SUMMARY_LIMIT = 160;

    /** @return list<string> what to fix, in an editor's words */
    public static function problems(Page $post): array
    {
        $summary = trim((string) $post->summary);

        return array_values(array_filter([
            $summary === '' ? 'Add a summary.' : null,
            mb_strlen($summary) > self::SUMMARY_LIMIT ? 'Shorten the summary to '.self::SUMMARY_LIMIT.' characters.' : null,
            $post->published_on === null ? 'Set a publication date.' : null,
        ]));
    }

    /**
     * Every post with something to fix, unpublished and hidden ones included, newest first.
     *
     * @return list<array{post: Page, problems: list<string>}>
     */
    public static function queue(): array
    {
        $queue = [];

        foreach (lynx()->pages->find('template=post, include=all, sort=-created') as $post) {
            $problems = self::problems($post);

            if ($problems !== []) {
                $queue[] = ['post' => $post, 'problems' => $problems];
            }
        }

        return $queue;
    }
}
// #endregion checks
