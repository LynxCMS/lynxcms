<?php

declare(strict_types=1);

namespace App\Bookings;

use Illuminate\Support\Facades\Cache;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Pages\Page;

// #region seats
/**
 * The seats left on a session live in its page's "seats" field, which staff also edit. Taking one
 * saves the page as the system: the buyer is not an editor, and the change shows in the page's
 * history as Atlas's own.
 */
final class Seats
{
    public static function areLeft(Page $session): bool
    {
        return $session->availability?->value === 'scheduled' && (int) $session->seats > 0 && $session->starts?->isFuture();
    }

    /** Takes one seat. A lock keeps two payments that arrive together from both taking the last. */
    public static function take(int $sessionId): void
    {
        Cache::lock("atlas:seats:{$sessionId}", 10)->block(5, function () use ($sessionId): void {
            lynx()->actingAs(Actor::system(), function () use ($sessionId): void {
                $session = lynx()->pages->get($sessionId);
                $session->seats = max(0, (int) $session->seats - 1);
                $session->save();
            });
        });
    }
}
// #endregion seats
