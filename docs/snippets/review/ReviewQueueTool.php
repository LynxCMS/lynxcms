<?php

declare(strict_types=1);

namespace App\Blog;

use LynxCms\Core\Agents\AgentReadTool;

// #region tool
/**
 * The review queue for MCP clients and the built-in agent: "which posts need work?"
 */
final class ReviewQueueTool extends AgentReadTool
{
    public static function name(): string
    {
        return 'blog_review_queue';
    }

    public static function description(): string
    {
        return 'List the blog posts that need work before publishing, with what to fix on each. '
            .'Fix them with pages_save, after a dry run.';
    }

    public static function permission(): string
    {
        return 'blog.review';
    }

    /** @param  array<string, mixed>  $arguments */
    public function handle(array $arguments): mixed
    {
        return array_map(fn (array $item): array => [
            'id' => $item['post']->id,
            'path' => $item['post']->path,
            'title' => (string) $item['post']->title,
            'problems' => $item['problems'],
        ], PostChecks::queue());
    }
}
// #endregion tool
