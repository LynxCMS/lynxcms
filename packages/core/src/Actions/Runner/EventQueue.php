<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use LynxCms\Core\Events\ContentChanged;

/**
 * The events an outer action queues, dispatched after it commits. A muted event is dropped when
 * queued unless an upkeep listener needs it; a muted content change adds its pages to the fold.
 *
 * @internal
 */
final class EventQueue
{
    /** @var list<array{0: object, 1: bool}> event, whether it is muted */
    private array $events = [];

    private PageFold $folded;

    public function __construct(private readonly Container $container)
    {
        $this->folded = new PageFold;
    }

    public function push(object $event): void
    {
        $muting = $this->muting();
        $isMuted = $muting->mutes($event);

        if ($isMuted && $event instanceof ContentChanged) {
            $this->folded->add($event->affectedPages());
        }

        if (! $isMuted || $muting->hasUpkeep($event)) {
            $this->events[] = [$event, $isMuted];
        }
    }

    /**
     * Empty the queue and return what dispatches its events, so the runner can finish first.
     *
     * @return Closure(): void
     */
    public function take(): Closure
    {
        $events = $this->events;
        $folded = $this->folded;
        $this->clear();

        return function () use ($events, $folded): void {
            $muting = $this->muting();

            foreach ($events as [$event, $isMuted]) {
                $muting->runUpkeep($event);

                if (! $isMuted) {
                    $this->container->make(Dispatcher::class)->dispatch($event);
                }
            }

            $muting->settle($folded);
        };
    }

    public function clear(): void
    {
        $this->events = [];
        $this->folded = new PageFold;
    }

    private function muting(): EventMuting
    {
        return $this->container->make(EventMuting::class);
    }
}
