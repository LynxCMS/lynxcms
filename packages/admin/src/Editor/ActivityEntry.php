<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Support\Value;

/**
 * One activity event as the editor lists it: when, who, and what the action did. The first change
 * says what happened; the rest (e.g. one path per moved page) are counted.
 *
 * @internal
 */
final class ActivityEntry
{
    /**
     * @return array{when: string, at: string, who: string, summary: string, more: int}
     */
    public static function from(ActivityEvent $event, ActorNames $names): array
    {
        return [
            'when' => $event->created_at->diffForHumans(),
            'at' => $event->created_at->toDayDateTimeString(),
            'who' => $names->label($event->actor_kind, $event->actor_id),
            'summary' => Value::string($event->changes[0]['summary'] ?? '', $event->action),
            'more' => max(0, count($event->changes) - 1),
        ];
    }
}
