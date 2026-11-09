<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor\Checks;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Support\Tables;

/**
 * Scheduled publishing that should have happened: lynx:schedule:run applies due times every
 * minute, so a schedule this late means Laravel's scheduler is not running.
 *
 * @internal
 */
final class Scheduler implements Check
{
    /** How late a schedule may be before it counts: a minute's run plus room for a slow one. */
    private const int MINUTES_LATE = 10;

    public function key(): string
    {
        return 'scheduler';
    }

    public function label(): string
    {
        return 'Scheduled publishing runs on time';
    }

    public function run(): array
    {
        $late = DB::table(Tables::SCHEDULES)->whereNull('failed_at')->where('at', '<', now()->subMinutes(self::MINUTES_LATE))->count();

        return $late === 0 ? [] : [new Finding(
            Severity::Error,
            "{$late} scheduled change(s) are more than ".self::MINUTES_LATE.' minutes late: Laravel\'s scheduler does not seem to run.',
            'Add the cron entry: * * * * * cd /path-to-your-site && php artisan schedule:run',
        )];
    }
}
