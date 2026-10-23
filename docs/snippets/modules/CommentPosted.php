<?php

declare(strict_types=1);

namespace App\Events;

/** Dispatched by the app's own comments code, after it saves a comment in its own table. */
final readonly class CommentPosted
{
    public function __construct(public int $postId) {}
}
