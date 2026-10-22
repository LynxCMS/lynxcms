<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use Closure;

/**
 * Before/after hooks on actions, the main extension point for modules.
 *
 * A before-hook receives (Input $input, ActionContext $context) and may return a new Input,
 * or throw ActionHalted to stop the action. An after-hook receives
 * (mixed $result, Input $input, ActionContext $context).
 *
 * @api
 */
final class HookRegistry
{
    /** @var array<string, list<Closure>> */
    private array $before = [];

    /** @var array<string, list<Closure>> */
    private array $after = [];

    /**
     * @param  class-string|'*'  $action
     */
    public function before(string $action, Closure $hook): self
    {
        $this->before[$action][] = $hook;

        return $this;
    }

    /**
     * @param  class-string|'*'  $action
     */
    public function after(string $action, Closure $hook): self
    {
        $this->after[$action][] = $hook;

        return $this;
    }

    public function runBefore(string $action, Input $input, ActionContext $context): Input
    {
        foreach ([...($this->before['*'] ?? []), ...($this->before[$action] ?? [])] as $hook) {
            $result = $hook($input, $context);

            if ($result instanceof Input) {
                $input = $result;
            }
        }

        return $input;
    }

    public function runAfter(string $action, mixed $result, Input $input, ActionContext $context): void
    {
        foreach ([...($this->after[$action] ?? []), ...($this->after['*'] ?? [])] as $hook) {
            $hook($result, $input, $context);
        }
    }

    public function flush(): void
    {
        $this->before = [];
        $this->after = [];
    }
}
