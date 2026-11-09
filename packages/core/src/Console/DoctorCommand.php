<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\DoctorChecks;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use Override;

/**
 * Runs every doctor check (ADR-0109) and reports what each found, with how to fix it. Fails when
 * a check finds an error, so CI and deploy scripts can stop on one; warnings only inform.
 */
final class DoctorCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:doctor {--json : Print the report as JSON}';

    #[Override]
    protected $description = 'Look for problems in the site\'s setup and content structure';

    public function handle(DoctorChecks $checks): int
    {
        $report = array_map(fn (Check $check): array => ['check' => $check, 'findings' => $check->run()], $checks->all());

        $this->option('json') ? $this->printJson($report) : $this->printReport($report);

        return $this->hasErrors($report) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array{check: Check, findings: list<Finding>}>  $report
     */
    private function printReport(array $report): void
    {
        foreach ($report as ['check' => $check, 'findings' => $findings]) {
            if ($findings === []) {
                $this->components->twoColumnDetail($check->label(), '<fg=green;options=bold>OK</>');

                continue;
            }

            foreach ($findings as $finding) {
                $this->printFinding($finding);
            }
        }
    }

    private function printFinding(Finding $finding): void
    {
        $finding->severity === Severity::Error ? $this->components->error($finding->message) : $this->components->warn($finding->message);

        if ($finding->fix !== null) {
            $this->line('  '.$finding->fix);
            $this->newLine();
        }
    }

    /**
     * @param  list<array{check: Check, findings: list<Finding>}>  $report
     */
    private function printJson(array $report): void
    {
        $this->line((string) json_encode(array_map(fn (array $entry): array => [
            'key' => $entry['check']->key(),
            'label' => $entry['check']->label(),
            'findings' => array_map(fn (Finding $finding): array => $finding->toArray(), $entry['findings']),
        ], $report), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  list<array{check: Check, findings: list<Finding>}>  $report
     */
    private function hasErrors(array $report): bool
    {
        foreach ($report as $entry) {
            foreach ($entry['findings'] as $finding) {
                if ($finding->severity === Severity::Error) {
                    return true;
                }
            }
        }

        return false;
    }
}
