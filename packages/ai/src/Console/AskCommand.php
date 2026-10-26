<?php

declare(strict_types=1);

namespace LynxCms\Ai\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Laravel\Prompts\Elements\Element;
use LynxCms\Ai\AgentRun;
use LynxCms\Ai\LynxAi;
use LynxCms\Ai\PlanTookTooLong;
use LynxCms\Core\Console\Concerns\AsksInTerminal;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Support\Value;
use Override;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\textarea;

/**
 * php artisan lynx:ask "Create a new course from this content and translate it to PT" --file=course.md
 */
final class AskCommand extends Command implements PromptsForMissingInput
{
    use AsksInTerminal;
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:ask {prompt : What you want done} {--file=* : Files to attach (Markdown, PDF...)} {--apply : Apply without asking}';

    #[Override]
    protected $description = 'Ask the Lynx AI agent to change the site; review its plan before it is applied';

    public function handle(LynxAi $ai): int
    {
        $files = array_map(fn (string $file): string => realpath($file) ?: $file, Value::strings($this->option('file')));
        $isApplying = (bool) $this->option('apply');

        try {
            // The plan is confirmed after the spinner stops: a prompt inside it would draw over it.
            $run = $this->working('Planning...', fn (): AgentRun => $ai->ask(Value::string($this->argument('prompt')), $files, apply: $isApplying));
        } catch (PlanTookTooLong $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($run->isEmpty()) {
            $this->line($run->text);
            $this->components->info('No changes were planned.');

            return self::SUCCESS;
        }

        $this->showPlan($run);

        if (! $run->isApplied() && confirm('Apply this plan?', ! $run->isDestructive())) {
            $run = $run->apply();
        }

        $run->isApplied()
            ? $this->components->info('Applied.')
            : $this->components->warn('Nothing was changed.');

        return self::SUCCESS;
    }

    /** @return array<string, Closure(): string> */
    #[Override]
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['prompt' => fn (): string => textarea('What should the agent do?', required: true)];
    }

    private function showPlan(AgentRun $run): void
    {
        $content = [$run->text];

        foreach ($run->steps as $index => $step) {
            $content[] = Element::heading(($index + 1).". {$step['tool']}");

            if ($step['changes'] !== []) {
                $content[] = Element::bulletedList($step['changes']);
            }
        }

        $this->box(
            label: $run->isDestructive() ? 'Plan, removes data' : 'Plan',
            content: $content,
            type: $run->isDestructive() ? 'warning' : null,
            info: count($run->steps).' step(s)',
        );
    }
}
