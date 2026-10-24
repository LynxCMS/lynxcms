<?php

declare(strict_types=1);

namespace LynxCms\Ai\Console;

use Illuminate\Console\Command;
use LynxCms\Ai\AgentRun;
use LynxCms\Ai\LynxAi;
use LynxCms\Ai\PlanTookTooLong;
use LynxCms\Core\Support\Value;
use Override;

/**
 * php artisan lynx:ask "Create a new course from this content and translate it to PT" --file=course.md
 */
final class AskCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:ask {prompt : What you want done} {--file=* : Files to attach (Markdown, PDF...)} {--apply : Apply without asking}';

    #[Override]
    protected $description = 'Ask the Lynx AI agent to change the site; review its plan before it is applied';

    public function handle(LynxAi $ai): int
    {
        $files = array_map(fn (string $f): string => realpath($f) ?: $f, Value::strings($this->option('file')));

        try {
            $run = $ai->ask((string) $this->argument('prompt'), $files, apply: (bool) $this->option('apply'), confirm: function (AgentRun $run): bool {
                $this->showPlan($run);

                if ($run->isDestructive()) {
                    $this->components->warn('This plan removes data.');
                }

                return $this->confirm('Apply this plan?', ! $run->isDestructive());
            });
        } catch (PlanTookTooLong $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($run->isApplied()) {
            if ($this->option('apply')) {
                $this->showPlan($run);
            }

            $this->components->info('Applied.');
        } elseif ($run->isEmpty()) {
            $this->line($run->text);
            $this->components->info('No changes were planned.');
        } else {
            $this->components->warn('Nothing was changed.');
        }

        return self::SUCCESS;
    }

    private function showPlan(AgentRun $run): void
    {
        $this->newLine();
        $this->line($run->text);
        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Plan</>', count($run->steps).' step(s)');

        foreach ($run->plan() as $line) {
            $this->line('  '.$line);
        }

        $this->newLine();
    }
}
