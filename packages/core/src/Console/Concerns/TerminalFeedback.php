<?php

declare(strict_types=1);

namespace LynxCms\Core\Console\Concerns;

use Closure;
use Laravel\Prompts\Elements\BulletedList;
use Laravel\Prompts\Elements\ElementContract;
use Laravel\Prompts\Elements\Heading;
use Laravel\Prompts\Elements\NumberedList;
use Laravel\Prompts\Support\Logger;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\task;

/**
 * Spinners, progress bars, tasks and callouts from Laravel Prompts, in a terminal only.
 *
 * Without a decorated output (a pipe, a cron or scheduler log, CI, Artisan::call(), tests) Prompts
 * still writes its frames, colours and cursor codes, so a scheduled lynx:backup would fill its log
 * with them. There the work runs bare and a callout becomes plain lines.
 *
 * @internal
 */
trait TerminalFeedback
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    protected function working(string $message, Closure $callback): mixed
    {
        return $this->output->isDecorated() ? $this->withQuietChild(fn (): mixed => spin($callback, $message)) : $callback();
    }

    /**
     * Runs the callback for each step, like array_map, with a progress bar.
     *
     * @template TStep
     * @template TResult
     *
     * @param  list<TStep>  $steps
     * @param  Closure(TStep): TResult  $callback
     * @return list<TResult>
     */
    protected function eachStep(string $label, array $steps, Closure $callback): array
    {
        if ($steps === [] || ! $this->output->isDecorated()) {
            return array_map($callback, $steps);
        }

        return array_values(progress($label, $steps, $callback));
    }

    /**
     * A spinner whose sub-label the callback updates through the Logger.
     *
     * @template TResult
     *
     * @param  Closure(Logger): TResult  $callback
     * @return TResult
     */
    protected function logging(string $label, Closure $callback): mixed
    {
        return $this->output->isDecorated() ? $this->withQuietChild(fn (): mixed => task($label, $callback)) : $callback(new Logger($label));
    }

    /**
     * A callout in a terminal; elsewhere its label and content as plain, indented lines.
     *
     * @param  list<string|ElementContract>  $content
     */
    protected function box(string $label, array $content, ?string $type = null, string $info = ''): void
    {
        if ($this->output->isDecorated()) {
            callout($label, $content, $type, $info);

            return;
        }

        $this->line("  {$label}");

        foreach ($content as $item) {
            array_map(fn (string $line) => $this->line("    {$line}"), $this->plainLines($item));
        }

        if ($info !== '') {
            $this->line("    ({$info})");
        }
    }

    /**
     * Spinners and tasks fork a child that draws the animation, then stop it with SIGHUP. A child
     * that inherits a PHP handler for SIGHUP (Testbench's console sets one, as does $this->trap())
     * runs it instead: a handler that exits shuts the child down cleanly, which ends the database
     * session the command still uses (on PostgreSQL the next query reconnects, or fails inside a
     * transaction), and one that returns leaves the child running. Forked with the default action,
     * the child dies without running any PHP.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $animation
     * @return TResult
     */
    private function withQuietChild(Closure $animation): mixed
    {
        if (! function_exists('pcntl_signal')) {
            return $animation();
        }

        $handler = pcntl_signal_get_handler(SIGHUP);
        pcntl_signal(SIGHUP, SIG_DFL);

        try {
            return $animation();
        } finally {
            pcntl_signal(SIGHUP, $handler);
        }
    }

    /** @return list<string> */
    private function plainLines(string|ElementContract $item): array
    {
        return match (true) {
            is_string($item) => [$item],
            $item instanceof Heading => [$item->text],
            $item instanceof BulletedList => array_values(array_map(fn (string $line): string => "- {$line}", $item->items)),
            $item instanceof NumberedList => array_values(array_map(fn (int $index, string $line): string => ($index + 1).". {$line}", array_keys($item->items), $item->items)),
            default => [],
        };
    }
}
