<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CommentPosted;
use LynxCms\Cache\Actions\ClearPageCache;
use LynxCms\Contracts\Actions\Actor;

// #region listener
/**
 * Comments live in the app's own table, so Lynx can't tell that one changes a post's page.
 * This listener clears the post's stored copies when a comment arrives.
 */
final class ClearPostOnComment
{
    public function handle(CommentPosted $event): void
    {
        // A signed-in reader may not clear caches, and actions run as the signed-in user, so run it as the system.
        lynx()->actingAs(Actor::system(), fn () => lynx()->run(ClearPageCache::class, ['pages' => [$event->postId]]));
    }
}
// #endregion listener
