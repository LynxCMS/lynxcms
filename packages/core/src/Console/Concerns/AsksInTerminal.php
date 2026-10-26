<?php

declare(strict_types=1);

namespace LynxCms\Core\Console\Concerns;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * For commands that ask questions: they ask only when a person is there to answer.
 *
 * Artisan::call() gives the command an interactive input, so a seeder or a deploy script run from
 * a terminal would otherwise stop on a prompt nobody expects. Missing arguments are asked for the
 * same way, so a command that implements PromptsForMissingInput still fails fast from a script.
 *
 * @internal
 */
trait AsksInTerminal
{
    /**
     * Whether a person is there to answer: the command was typed in a terminal, or
     * SHELL_INTERACTIVE says so, as Symfony allows. Artisan::call() from a seeder, a deploy script
     * or a test never asks, since nobody could answer and Laravel Prompts would either wait on
     * STDIN or answer the default for them.
     */
    protected function canAsk(): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        return getenv('SHELL_INTERACTIVE') !== false
            || ($this->input instanceof ArgvInput && defined('STDIN') && stream_isatty(STDIN));
    }

    protected function promptForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($this->canAsk()) {
            parent::promptForMissingArguments($input, $output);
        }
    }
}
