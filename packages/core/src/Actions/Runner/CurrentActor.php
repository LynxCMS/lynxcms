<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use LynxCms\Contracts\Actions\Actor;

/**
 * Who the action runner acts as (ActionRunner::actor()): the actor a caller set with actingAs(),
 * else the signed-in user, else the command line, or a visitor while a web request is served.
 *
 * @internal
 */
final class CurrentActor
{
    private ?Actor $actor = null;

    private bool $isServingRequest = false;

    public function __construct(private readonly Container $container) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function actingAs(Actor $actor, Closure $callback): mixed
    {
        $previous = $this->actor;
        $this->actor = $actor;

        try {
            return $callback();
        } finally {
            $this->actor = $previous;
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function servingRequest(Closure $callback): mixed
    {
        $previous = $this->isServingRequest;
        $this->isServingRequest = true;

        try {
            return $callback();
        } finally {
            $this->isServingRequest = $previous;
        }
    }

    public function resolve(): Actor
    {
        if ($this->actor !== null) {
            return $this->actor;
        }

        $id = $this->container->make(AuthFactory::class)->guard()->id();

        if ($id !== null) {
            return Actor::user($id);
        }

        return $this->container->runningInConsole() && ! $this->isServingRequest ? Actor::cli() : Actor::guest();
    }
}
