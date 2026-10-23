<?php

declare(strict_types=1);

namespace App\Lynx\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SetPageStatus;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Page;

// #region action
/**
 * Archives a blog post: it keeps its URL, but leaves the blog's lists, menus and search.
 */
final class ArchivePost extends Action
{
    public static function name(): string
    {
        return 'posts.archive';
    }

    public static function description(): string
    {
        return 'Archive a blog post: it keeps its URL but leaves lists, menus and search.';
    }

    public static function permission(): string
    {
        return 'pages.status';
    }

    public static function rules(): array
    {
        return ['post' => ['required', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['post' => $schema->string()->description('Path or id of the post.')->required()];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $post = lynx()->pages->get((string) $input->string('post'));

        if ($post === null || $post->template()->name !== 'post') {
            throw new InvalidArgumentException("[{$input->string('post')}] is not a blog post.");
        }

        // Other actions run inside this one: one transaction, one entry in the activity log.
        return $context->run(SetPageStatus::class, ['page' => $post->id, 'add' => ['hidden']]);
    }
}
// #endregion action
