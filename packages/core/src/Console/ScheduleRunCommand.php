<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Actions\Pages\RunSchedules;
use LynxCms\Core\Support\Value;
use Override;

/** Applies due scheduled publishing (ADR-0036). Laravel's scheduler runs it every minute. */
final class ScheduleRunCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:schedule:run';

    #[Override]
    protected $description = 'Publish and unpublish pages whose scheduled time has come';

    public function handle(): int
    {
        $result = Value::map(lynx()->run(RunSchedules::class)->value);
        $done = Value::int($result['done'] ?? 0);
        $failed = Value::int($result['failed'] ?? 0);

        $this->components->info("Scheduled publishing: {$done} done, {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
