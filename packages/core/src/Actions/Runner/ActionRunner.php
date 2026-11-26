<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\DetectsConcurrencyErrors;
use InvalidArgumentException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Schema\SchemaEditing;
use LynxCms\Core\Support\Flushable;
use Throwable;

/**
 * Runs every write in Lynx: validation, transaction, before-hooks, the action, after-hooks,
 * dry-run rollback and domain events after commit. An outer action that opened the outermost
 * transaction runs again when the database ends it to break a deadlock, as
 * DB::transaction($callback, attempts:) does.
 *
 * @api
 */
final class ActionRunner
{
    use DetectsConcurrencyErrors;

    /** Tries of an outer action that keeps meeting deadlocks or serialization failures. */
    public const int ATTEMPTS = 3;

    private int $depth = 0;

    private ?ChangeSet $changes = null;

    private bool $dryRun = false;

    private readonly EventQueue $events;

    private readonly CurrentActor $actors;

    public function __construct(
        private readonly Container $container,
        private readonly HookRegistry $hooks,
        private readonly DatabaseManager $db,
    ) {
        $this->events = new EventQueue($container);
        $this->actors = new CurrentActor($container);
    }

    /**
     * @param  class-string  $action
     * @param  array<string, mixed>|Input  $input
     */
    public function run(string $action, array|Input $input = [], bool $dryRun = false): ActionResult
    {
        if (! is_subclass_of($action, Action::class)) {
            throw new InvalidArgumentException("[{$action}] is not a Lynx action.");
        }

        // Inside someone else's transaction a deadlock has ended theirs too: only they can retry.
        $mayRetry = $this->depth === 0 && $this->opensOutermostTransaction();

        for ($try = 1; ; $try++) {
            try {
                return $this->attempt($action, $input, $dryRun);
            } catch (Throwable $e) {
                if (! $mayRetry || $try >= self::ATTEMPTS || ! $this->causedByConcurrencyError($e)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * One try: a failed one rolls back its writes and drops its changes and events (finish()).
     *
     * @param  class-string<Action>  $action
     * @param  array<string, mixed>|Input  $input
     */
    private function attempt(string $action, array|Input $input, bool $dryRun): ActionResult
    {
        $outer = $this->depth === 0;

        // Permissions first (ADR-0033), before validation: an actor who may not run the action
        // learns nothing from its validation messages, such as whether a name is taken. Nested
        // actions were authorized with their outer action.
        if ($outer) {
            $this->authorizeOuter($action);
            $this->changes = new ChangeSet;
            $this->events->clear();
            $this->dryRun = $dryRun;
        }

        $input = ValidatedInput::for($action, $input);

        $context = new ActionContext(
            action: $action,
            actor: $this->actor(),
            dryRun: $this->dryRun,
            depth: $this->depth + 1,
            changes: $this->changes ?? new ChangeSet,
            runner: $this,
        );

        $connection = $this->db->connection();
        $level = $connection->transactionLevel();
        $this->depth++;

        try {
            // Inside the try: when the transaction fails to start, depth must go back down, or every
            // later action in a long-lived process would count as nested and skip its checks.
            $connection->beginTransaction();

            // At any depth: a module's action may create fields inside (ADR-0117).
            if ($action::changesSchema()) {
                $this->container->make(SchemaEditing::class)->authorize($context->actor, $action::name());
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
            if ($connection->transactionLevel() > $level) {
                $connection->rollBack();
            }

            $this->depth--;

            if ($outer) {
                $this->finish();
            }

            throw $e;
        }

        $this->depth--;
        $changes = $context->changes;

        if ($outer) {
            $dispatch = $this->events->take();
            $isDryRun = $this->dryRun;
            $this->finish();

            if (! $isDryRun) {
                $dispatch();
            }
        }

        return new ActionResult($action, $value, $changes, $context->dryRun);
    }

    /**
     * A refused action leaves the caches as a failed one does: emptied, so a page object the caller
     * changed before saving is read again from the database, not from memory.
     *
     * @param  class-string<Action>  $action
     */
    private function authorizeOuter(string $action): void
    {
        try {
            $this->container->make(Authorizer::class)->authorizeAction($this->actor(), $action::permission(), $action::name());
        } catch (Throwable $e) {
            $this->flushCaches();

            throw $e;
        }
    }

    /**
     * Whether the transaction an action opens now is the outermost one, by the rule Laravel's
     * transaction manager keeps (a test's own transaction does not count).
     */
    private function opensOutermostTransaction(): bool
    {
        $level = $this->db->connection()->transactionLevel();
        $transactions = $this->container->bound('db.transactions') ? $this->container->make('db.transactions') : null;

        return $transactions instanceof DatabaseTransactionsManager ? $transactions->afterCommitCallbacksShouldBeExecuted($level) : $level === 0;
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
        return $this->actors->actingAs($actor, $callback);
    }

    public function actor(): Actor
    {
        return $this->actors->resolve();
    }

    /**
     * Run a web request: with nobody signed in its actions are a visitor's, even where the app
     * also counts as running in the console (tests, a request handled from a command).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function servingRequest(Closure $callback): mixed
    {
        return $this->actors->servingRequest($callback);
    }

    public function queueEvent(object $event): void
    {
        $this->events->push($event);
    }

    public function isRunning(): bool
    {
        return $this->depth > 0;
    }

    private function finish(): void
    {
        $this->changes = null;
        $this->events->clear();
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
