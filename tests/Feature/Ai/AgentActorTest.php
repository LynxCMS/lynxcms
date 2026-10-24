<?php

declare(strict_types=1);

use Laravel\Ai\Responses\Data\ToolCall;
use LynxCms\Ai\Agents\CmsAgent;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Agents\AgentReadTool;
use LynxCms\Core\Agents\ReadToolRegistry;

/*
 * The agent reads as the actor it works for, like it writes: a run for a user with a narrow role
 * started from a command or a queued job (which run as the console) never reads with the
 * console's rights.
 */

final class WhoReadsTool extends AgentReadTool
{
    /** @var list<string> */
    public static array $readers = [];

    public static function name(): string
    {
        return 'who_reads';
    }

    public static function description(): string
    {
        return 'Names the actor reading.';
    }

    public function handle(array $arguments): mixed
    {
        $actor = app(ActionRunner::class)->actor();
        self::$readers[] = $actor->kind.':'.$actor->id;

        return 'ok';
    }
}

beforeEach(function (): void {
    courseSite();
    WhoReadsTool::$readers = [];
    app(ReadToolRegistry::class)->register(WhoReadsTool::class);
});

it('runs read tools as the actor the run is for, not the console', function (): void {
    CmsAgent::fake([new ToolCall('1', 'who_reads', []), 'Done.']);

    lynx()->ai()->ask('Who is reading?', actor: Actor::ai(5));

    expect(WhoReadsTool::$readers)->toBe(['ai:5'])
        ->and(app(ActionRunner::class)->actor()->kind)->toBe(Actor::CLI);
});
