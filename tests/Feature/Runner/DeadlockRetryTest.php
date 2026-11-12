<?php

declare(strict_types=1);

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\LanguageCreated;
use LynxCms\Core\Models\Language;

/*
 * A database can end a transaction to break a deadlock (or a serialization failure), expecting
 * the client to try again. The runner owns the transaction of an outer action, so it runs the
 * action again from the start, a few times, as DB::transaction($callback, attempts:) does. The
 * failed try leaves nothing behind: its writes are rolled back and its events dropped.
 */

/** Adds a language, then fails ($error, a deadlock by default) while DeadlockingAction::$failures remain. */
final class DeadlockingAction extends Action
{
    public static int $failures = 0;

    public static int $tries = 0;

    public static string $error = 'Deadlock found when trying to get lock; try restarting transaction';

    public static function name(): string
    {
        return 'tests.deadlocking';
    }

    public static function description(): string
    {
        return 'For tests.';
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Input $input, ActionContext $context): int
    {
        self::$tries++;
        $context->run(CreateLanguage::class, ['code' => 'xx', 'name' => 'Test']);

        if (self::$failures-- > 0) {
            throw new QueryException('testing', 'update lynx_pages', [], new PDOException(self::$error));
        }

        return self::$tries;
    }
}

beforeEach(function (): void {
    installLynx();
    DeadlockingAction::$tries = 0;
    DeadlockingAction::$error = 'Deadlock found when trying to get lock; try restarting transaction';
});

it('runs an action again after a deadlock, keeping only the try that worked', function (): void {
    DeadlockingAction::$failures = 1;
    Event::fake([LanguageCreated::class]);

    $result = app(ActionRunner::class)->run(DeadlockingAction::class);

    expect($result->value)->toBe(2)
        ->and(Language::query()->where('code', 'xx')->count())->toBe(1);
    Event::assertDispatchedTimes(LanguageCreated::class, 1);
});

it('gives up after the last try and throws the deadlock', function (): void {
    DeadlockingAction::$failures = ActionRunner::ATTEMPTS;

    expect(fn () => app(ActionRunner::class)->run(DeadlockingAction::class))->toThrow(QueryException::class)
        ->and(DeadlockingAction::$tries)->toBe(ActionRunner::ATTEMPTS)
        ->and(Language::query()->where('code', 'xx')->exists())->toBeFalse();
});

it('leaves the retry to whoever opened the transaction around the action', function (): void {
    DeadlockingAction::$failures = 1;

    // DB::transaction() turns the deadlock into its own DeadlockException for the code around it.
    expect(fn () => DB::transaction(fn () => app(ActionRunner::class)->run(DeadlockingAction::class)))->toThrow(DeadlockException::class)
        ->and(DeadlockingAction::$tries)->toBe(1);
});

it('does not run an action again after other database errors', function (): void {
    DeadlockingAction::$failures = 1;
    DeadlockingAction::$error = 'no such column: missing';

    expect(fn () => app(ActionRunner::class)->run(DeadlockingAction::class))->toThrow(QueryException::class)
        ->and(DeadlockingAction::$tries)->toBe(1);
});
