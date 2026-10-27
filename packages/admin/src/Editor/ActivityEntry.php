<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Activity\ActivityEvent;

/**
 * One activity event as the editor lists it: when, who, and what the action did, in parts the
 * activity-summary component words (ActivityDescription).
 *
 * @internal
 */
final class ActivityEntry
{
    /**
     * @return array{when: string, at: string, who: string, what: array<string, mixed>}
     */
    public static function from(ActivityEvent $event, ActorNames $names): array
    {
        return [
            'when' => $event->created_at->diffForHumans(),
            'at' => $event->created_at->toDayDateTimeString(),
            'who' => $names->label($event->actor_kind, $event->actor_id),
            'what' => (new ActivityDescription)->of($event),
        ];
    }
}
