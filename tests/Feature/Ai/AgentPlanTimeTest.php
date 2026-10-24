<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\Data\ToolCall;
use LynxCms\Ai\Agents\CmsAgent;
use LynxCms\Ai\PlanTookTooLong;

/*
 * A plan runs inside one database transaction, which keeps other writers waiting on SQLite. A
 * run that passes lynx.ai.plan_seconds stops at its next tool call, and everything rolls back.
 */

beforeEach(function (): void {
    courseSite();
    config(['lynx.ai.plan_seconds' => 60]);
});

it('stops a plan that runs past its time and writes nothing', function (): void {
    CmsAgent::fake(function (): ToolCall|string {
        static $step = 0;

        return match ($step++) {
            0 => new ToolCall('1', 'fields_create', ['name' => 'client', 'type' => 'text']),
            1 => (function (): ToolCall {
                Carbon::setTestNow(now()->addSeconds(61));

                return new ToolCall('2', 'fields_create', ['name' => 'budget', 'type' => 'integer']);
            })(),
            default => 'Done.',
        };
    });

    expect(fn () => lynx()->ai()->ask('Add client and budget fields', apply: true))->toThrow(PlanTookTooLong::class, 'more than 60 seconds')
        ->and(lynx()->fields->find('client'))->toBeNull()
        ->and(lynx()->fields->find('budget'))->toBeNull();
});

it('lets a plan within its time finish', function (): void {
    CmsAgent::fake([new ToolCall('1', 'fields_create', ['name' => 'client', 'type' => 'text']), 'Done.']);

    lynx()->ai()->ask('Add a client field', apply: true);

    expect(lynx()->fields->find('client'))->not->toBeNull();
});

it('asks for approval after the plan rolled back, then replays it', function (): void {
    CmsAgent::fake([new ToolCall('1', 'fields_create', ['name' => 'client', 'type' => 'text']), 'Done.']);
    $seen = [];
    $level = DB::transactionLevel();

    $run = lynx()->ai()->ask('Add a client field', confirm: function () use (&$seen): bool {
        $seen = ['level' => DB::transactionLevel(), 'field' => lynx()->fields->find('client')];

        return true;
    });

    expect($seen)->toBe(['level' => $level, 'field' => null])
        ->and($run->isApplied())->toBeTrue()
        ->and(lynx()->fields->find('client'))->not->toBeNull();
});
