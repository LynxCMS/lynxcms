<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use Closure;

/**
 * Fluent helper returned by Lynx::hook(SavePage::class).
 *
 * @api
 */
final readonly class HookBuilder
{
    /**
     * @param  class-string|'*'  $action
     */
    public function __construct(private HookRegistry $hooks, private string $action) {}

    public function before(Closure $hook): self
    {
        $this->hooks->before($this->action, $hook);

        return $this;
    }

    public function after(Closure $hook): self
    {
        $this->hooks->after($this->action, $hook);

        return $this;
    }
}
