<?php

declare(strict_types=1);

namespace LynxCms\Admin\References;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\RebuildPageReferences;
use LynxCms\Core\Actions\Runner\ActionResult;

/**
 * Runs page_references.rebuild on the queue, as the user who asked for it, so a large site's
 * rebuild does not hold up the admin. With the sync queue it runs at once.
 *
 * @internal
 */
final class RebuildIndex implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public readonly int|string $userId) {}

    public function handle(): void
    {
        lynx()->actingAs(Actor::user($this->userId), fn (): ActionResult => lynx()->run(RebuildPageReferences::class));
    }
}
