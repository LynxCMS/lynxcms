<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Muting of action events (lynx()->withoutEvents(), ADR-0145), and the upkeep listeners that keep
 * Lynx's stored data correct (sort keys, the search index, media folders), which every event
 * reaches after commit, muted or not.
 *
 * @internal
 */
final class EventMuting
{
    /** @var list<list<class-string>|null> one entry per running callback; null mutes every event */
    private array $scopes = [];

    /** @var array<class-string, list<class-string|Closure(object): mixed>> */
    private array $upkeep = [];

    private PageFold $pending;

    public function __construct(private readonly Container $container)
    {
        $this->pending = new PageFold;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  list<class-string>  $events  the classes to mute; empty mutes every event
     * @return T
     */
    public function run(Closure $callback, array $events = []): mixed
    {
        $this->scopes[] = $events === [] ? null : $events;

        try {
            return $callback();
        } finally {
            array_pop($this->scopes);

            if ($this->scopes === []) {
                $this->release();
            }
        }
    }

    public function mutes(object $event): bool
    {
        foreach ($this->scopes as $classes) {
            if ($classes === null) {
                return true;
            }

            foreach ($classes as $class) {
                if ($event instanceof $class) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A listener that keeps stored data correct: it runs for these events even while they are
     * muted, after commit, before the app's listeners.
     *
     * @param  class-string|list<class-string>  $events
     * @param  class-string|Closure(object): mixed  $listener  a class with handle($event), or a closure
     */
    public function upkeep(string|array $events, string|Closure $listener): void
    {
        foreach ((array) $events as $event) {
            $this->upkeep[$event][] = $listener;
        }
    }

    public function hasUpkeep(object $event): bool
    {
        return $this->listenersFor($event) !== [];
    }

    public function runUpkeep(object $event): void
    {
        foreach ($this->listenersFor($event) as $listener) {
            if ($listener instanceof Closure) {
                $listener($event);
            } else {
                $handle = [$this->container->make($listener), 'handle'];

                if (is_callable($handle)) {
                    $handle($event);
                }
            }
        }
    }

    /** Pages of muted changes that committed: held while a callback still mutes, otherwise dispatched now. */
    public function settle(PageFold $fold): void
    {
        if ($this->scopes !== []) {
            $this->pending->merge($fold);
        } elseif (! $fold->isEmpty()) {
            $this->container->make(Dispatcher::class)->dispatch($fold->event());
        }
    }

    /** The last callback ended: one ContentBatchChanged for what was folded, queued when an action still runs. */
    private function release(): void
    {
        if ($this->pending->isEmpty()) {
            return;
        }

        $event = $this->pending->event();
        $this->pending = new PageFold;
        $runner = $this->container->make(ActionRunner::class);

        $runner->isRunning() ? $runner->queueEvent($event) : $this->container->make(Dispatcher::class)->dispatch($event);
    }

    /**
     * @return list<class-string|Closure(object): mixed>
     */
    private function listenersFor(object $event): array
    {
        $listeners = [];

        foreach ($this->upkeep as $class => $classListeners) {
            if ($event instanceof $class) {
                array_push($listeners, ...$classListeners);
            }
        }

        return $listeners;
    }
}
