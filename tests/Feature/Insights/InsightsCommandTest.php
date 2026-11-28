<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\ActionTool;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * lynx:insights writes a report now, or lists what it would suggest with --dry-run; the scheduler
 * runs it when lynx-insights.schedule says when. MCP clients get the actions as tools.
 */

beforeEach(function (): void {
    courseSite();
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add(new FixedSource('fixed', [new Insight('Expand the courses', 'Read by many.')]));
});

/** @return list<Event> */
function insightEvents(): array
{
    app()->forgetInstance(Schedule::class);

    return array_values(array_filter(app(Schedule::class)->events(), fn (Event $event): bool => str_contains((string) $event->command, 'lynx:insights')));
}

it('writes a report', function (): void {
    $this->artisan('lynx:insights')->expectsOutputToContain('Expand the courses')->assertSuccessful();

    expect(InsightReport::query()->count())->toBe(1);
});

it('lists the insights without storing them on a dry run', function (): void {
    $this->artisan('lynx:insights', ['--dry-run' => true])->expectsOutputToContain('Expand the courses')->assertSuccessful();

    expect(InsightReport::query()->count())->toBe(0);
});

it('is not scheduled by default', function (): void {
    expect(insightEvents())->toBe([]);
});

it('is scheduled monthly or by a cron expression', function (string $schedule, string $expression): void {
    config(['lynx-insights.schedule' => $schedule]);

    expect(insightEvents())->toHaveCount(1)
        ->and(insightEvents()[0]->expression)->toBe($expression);
})->with([
    'monthly' => ['monthly', '0 0 1 * *'],
    'cron' => ['0 6 1 * *', '0 6 1 * *'],
]);

it('gives MCP clients a tool to write a report', function (): void {
    LynxServer::tool(new ActionTool(GenerateInsights::class), ['dry_run' => true])->assertOk()->assertSee('Expand the courses');
});
