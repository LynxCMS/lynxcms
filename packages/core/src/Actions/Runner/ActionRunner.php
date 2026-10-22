<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Value;
use Throwable;

/**
 * Runs every write in Lynx: validation, transaction, before-hooks, the action, after-hooks,
 * dry-run rollback and domain events after commit.
 *
 * @api
 */
final class ActionRunner
{
    private int $depth = 0;

    private ?ChangeSet $changes = null;

    private bool $dryRun = false;

    /** @var list<object> */
    private array $events = [];

    private ?Actor $actor = null;

    public function __construct(
        private readonly Container $container,
        private readonly HookRegistry $hooks,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  class-string  $action
     * @param  array<string, mixed>|Input  $input
     */
    public function run(string $action, array|Input $input = [], bool $dryRun = false): ActionResult
    {
        if (! is_subclass_of($action, Action::class)) {
            throw new InvalidArgumentException("[{$action}] is not a Lynx action.");
        }

        $outer = $this->depth === 0;

        if ($outer) {
            $this->changes = new ChangeSet;
            $this->events = [];
            $this->dryRun = $dryRun;
        }

        $data = $input instanceof Input ? $input->all() : $input;
        // Validated values win, but recursively: rules for some nested keys ("settings.description")
        // must not drop their siblings ("settings.format") that the action validates itself.
        $input = new Input(Value::map(array_replace_recursive($data, Validator::make($data, $action::rules())->validate())));

        $context = new ActionContext(
            action: $action,
            actor: $this->actor(),
            dryRun: $this->dryRun,
            depth: $this->depth + 1,
            changes: $this->changes ?? new ChangeSet,
            runner: $this,
        );

        $connection = $this->db->connection();
        $this->depth++;
        $connection->beginTransaction();

        try {
            // Permissions first (ADR-0033); nested actions were authorized with their outer action.
            if ($outer && is_subclass_of($action, Action::class)) {
                $this->container->make(Authorizer::class)->authorizeAction($context->actor, $action::permission(), $action::name());
            }

            $input = $this->hooks->runBefore($action, $input, $context);
            $instance = $this->container->make($action);

            if (! $instance instanceof Action) {
                throw new InvalidArgumentException("[{$action}] did not resolve to a Lynx action.");
            }

            $value = $instance->handle($input, $context);
            $this->hooks->runAfter($action, $value, $input, $context);

            if ($outer && $this->dryRun) {
                $connection->rollBack();
            } else {
                $connection->commit();
            }
        } catch (Throwable $e) {
            $connection->rollBack();
            $this->depth--;

            if ($outer) {
                $this->finish();
            }

            throw $e;
        }

        $this->depth--;
        $changes = $context->changes;

        if ($outer) {
            $events = $this->takeEvents();
            $dispatch = ! $this->dryRun;
            $this->finish();

            foreach ($dispatch ? $events : [] as $event) {
                $this->container->make(Dispatcher::class)->dispatch($event);
            }
        }

        return new ActionResult($action, $value, $changes, $context->dryRun);
    }

    /**
     * Run a callback with a specific actor (MCP client, AI agent, a user).
     *
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

    public function actor(): Actor
    {
        if ($this->actor !== null) {
            return $this->actor;
        }

        $auth = $this->container->make(AuthFactory::class);
        $id = $auth->guard()->id();

        if ($id !== null) {
            return Actor::user($id);
        }

        return $this->container->runningInConsole() ? Actor::cli() : Actor::system();
    }

    public function queueEvent(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<object>
     */
    private function takeEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function isRunning(): bool
    {
        return $this->depth > 0;
    }

    private function finish(): void
    {
        $this->changes = null;
        $this->events = [];
        $this->dryRun = false;
        $this->flushCaches();
    }

    /**
     * Clear every request-level cache (languages, fields, templates, pages, paths). Called after
     * each outer action, and by callers that roll back a transaction around several actions.
     */
    public function flushCaches(): void
    {
        foreach ($this->container->tagged('lynx.flushable') as $service) {
            if ($service instanceof Flushable) {
                $service->flush();
            }
        }
    }
}
